import { Injectable, Logger } from "@nestjs/common";
import { openai } from "@ai-sdk/openai";
import { loggedGenerateText } from "../logging/logged-generate-text";
import { createGetCarDataTool } from "../tools/get-car-data.tool";
import { createAskUserTool } from "../tools/ask-user.tool";
import { VehicleEnrichmentService } from "./vehicle-enrichment.service";
import { EventPublisherService } from "../messaging/event-publisher.service";
import { mapCarDataToFields } from "../mappers/car-data.mapper";

type ImageInput = { path: string; url: string };

type ImageGroup = {
  group_name: string;
  description: string;
  images: string[];
  category?: string;
  condition?: string;
  extracted_fields?: Record<string, string | number>;
  missing_fields?: string[];
  vehicleData?: Record<string, unknown>;
};

export class NeedsInputError extends Error {
  constructor() {
    super("group_images suspended: waiting for vehicle identifier from user");
    this.name = "NeedsInputError";
  }
}

const BATCH_SIZE = 10;

const SYSTEM_PROMPT = `If any image shows a vehicle (car, motorcycle, etc.):
1. Look for a license plate (SPZ) or VIN physically visible as text on a plate/frame IN the image.
   Do NOT use the image filename or path as an identifier.
2. If a real plate/VIN is clearly readable in the image, call get_car_data with that value.
   - If get_car_data returns found=false, call ask_user (see step 3).
3. If no plate/VIN is visible or get_car_data returned found=false, call ask_user ONCE with:
   - inputType: "vin_or_spz"
   - prompt: "Zadejte SPZ nebo VIN kód vozidla:"
   - imageUrls: array with ONLY the display URLs of the vehicle images (use the "Display URL" shown next to each image, NOT the File path). Do NOT include non-vehicle images.
   After calling ask_user, STOP — do not output any JSON. The job will be resumed later.
4. Once you have vehicle data (found=true), include technical details (brand, model, year, displacement, power kW, fuel) in the description.
Always obtain vehicle data before writing descriptions for vehicle images.

You are given a list of images with their file paths. For each image, identify the main object depicted and group images by INDIVIDUAL object identity — each group must contain images of exactly ONE specific item.
CRITICAL: Different individual items must be in SEPARATE groups, even if they are the same type. For example:
- N different items → N groups (one per items)
- A car exterior + interior of THE SAME car → 1 group
- A motorcycle from the front + from the side (same motorcycle) → 1 group
Use visual cues (color, brand, model, license plate, background, distinctive features) to distinguish between different individual items of the same type.

You MUST classify each group into one of these categories:
car, truck, motorcycle, electronics, mobile_phone, clothing, home_garden, children_goods, sport, books_media, photo_video, computer, moto_parts, tools, other

You MUST assess the condition of each item from these values:
new, like_new, very_good, good, acceptable, needs_repair
Determine condition from visible wear, damage, packaging, etc. in the photos.

Extract any fields you can determine from the photos:
- brand: visible logo or text (vehicles, electronics, clothing)
- color: dominant color of the item
- body_type: for vehicles (sedan, hatchback, combi, suv, mpv, cabrio, pickup, van)
- moto_type: for motorcycles (sport, touring, enduro, chopper, naked, scooter, cross, atv, trial, supermoto)
- gender: for clothing (male, female, unisex, boy, girl)
Include only fields you can confidently determine from the images.

Report required fields you could NOT determine. Required fields per category:
- car/truck/motorcycle: brand, model, year, condition
- electronics/photo_video/computer: brand, condition
- mobile_phone: brand, model, condition
- clothing: size, condition
- home_garden/books_media/moto_parts/tools/other: condition
- children_goods/sport: item_type, condition

Output a JSON array with the following structure:
[
  {
    "group_name": "<Concise name of the object category in Czech, max 50 characters>",
    "description": "<Selling and informative description of the object(s) in Czech, max 1000 characters>",
    "images": ["<original file path 1>", "<original file path 2>"],
    "category": "<category enum value>",
    "condition": "<condition enum value>",
    "extracted_fields": { "brand": "...", "color": "..." },
    "missing_fields": ["field1", "field2"]
  }
]
Requirements:
- The group_name must be concise, in Czech, up to 50 characters.
- The description must be in Czech, up to 1000 characters.
- The description MUST include the visible condition of the item (e.g. novy, jako novy, dobry stav, opotrebene, poskozene). Assess condition from the images carefully.
- The description should contain important product information (brand, model, key features, visible defects) and be written in a persuasive style suitable for a classified ad.
- Use exactly the original file paths as given.
- Within each group's images array, sort the images from BEST to WORST cover photo quality. The first image must be the best candidate for the main listing photo: object fully visible, well-lit, centered, in focus, no heavy cropping. Put blurry, dark, partially visible, or heavily cropped images last.
- Output only valid JSON. Do not wrap it in markdown, do not include any explanation or extra text. Return only the raw JSON.`;

@Injectable()
export class GroupImagesService {
  private readonly logger = new Logger(GroupImagesService.name);

  constructor(
    private readonly vehicleEnrichmentService: VehicleEnrichmentService,
    private readonly eventPublisher: EventPublisherService,
  ) {}

  async groupAndDescription(
    images: ImageInput[],
    jobId?: string,
    articleId?: number | null,
    vehicleIdentifier?: string,
    condition?: string,
  ): Promise<{ groups: ImageGroup[]; tokensUsed: number }> {
    if (images.length === 0) return { groups: [], tokensUsed: 0 };

    const chunks: ImageInput[][] = [];
    for (let i = 0; i < images.length; i += BATCH_SIZE) {
      chunks.push(images.slice(i, i + BATCH_SIZE));
    }

    const allGroups: ImageGroup[] = [];
    let totalTokensUsed = 0;
    for (const chunk of chunks) {
      const { groups, tokensUsed } = await this.processImageBatch(chunk, jobId, articleId ?? null, vehicleIdentifier, condition);
      allGroups.push(...groups);
      totalTokensUsed += tokensUsed;
    }

    return { groups: allGroups, tokensUsed: totalTokensUsed };
  }

  private async downloadAsBuffer(url: string): Promise<Buffer> {
    const res = await fetch(url, { redirect: "follow" });
    if (!res.ok) throw new Error(`Failed to download ${url}: ${res.status}`);
    return Buffer.from(await res.arrayBuffer());
  }

  private async processImageBatch(
    images: ImageInput[],
    jobId?: string,
    articleId: number | null = null,
    vehicleIdentifier?: string,
    condition?: string,
  ): Promise<{ groups: ImageGroup[]; tokensUsed: number }> {
    this.logger.log("Processing batch of %d images", images.length);

    const content: Array<
      | { type: "text"; text: string }
      | { type: "image"; image: Uint8Array; mimeType: string }
    > = [];

    if (condition) {
      content.push({
        type: "text",
        text: `Stav zbozi zadany uzivatelem: "${condition}". Pouzij tento stav jako "condition" hodnotu ve vystupu — neprepisuj ho vlastnim odhadem z fotek. Nezarazuj "condition" do missing_fields.`,
      });
    }

    if (vehicleIdentifier) {
      content.push({
        type: "text",
        text: `Vehicle identifier (SPZ/VIN) provided by user: ${vehicleIdentifier}. Call get_car_data with this identifier first. If get_car_data returns found=false, write the description WITHOUT technical data — do NOT call ask_user again.`,
      });
    }

    for (const img of images) {
      const buffer = await this.downloadAsBuffer(img.url);
      const displayUrl = "/" + img.path.replace(/^\//, "");
      content.push({ type: "image", image: new Uint8Array(buffer), mimeType: "image/jpeg" });
      content.push({ type: "text", text: `File path: ${img.path}\nDisplay URL: ${displayUrl}` });
    }

    // Always include ask_user when we have a jobId (articleId can be null for batch context)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const tools: Record<string, any> = {
      get_car_data: createGetCarDataTool(this.vehicleEnrichmentService),
    };
    if (jobId !== undefined) {
      tools["ask_user"] = createAskUserTool(this.eventPublisher, jobId, articleId);
    }

    const result = await loggedGenerateText({
      model: openai("gpt-5-mini"),
      system: SYSTEM_PROMPT,
      messages: [{ role: "user", content }],
      tools,
      maxSteps: 4,
    }, "group-images");

    // If LLM called ask_user, the job is suspended — caller handles NeedsInputError
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const askUserCalled = result.steps?.some((s: any) =>
      s.toolCalls?.some((tc: any) => tc.toolName === "ask_user"),
    );
    if (askUserCalled) {
      throw new NeedsInputError();
    }

    this.logger.debug("Group images raw response: %s", result.text);

    // Extract vehicleData from get_car_data tool result if present
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const allToolResults: any[] = result.steps?.flatMap((s: any) => (s as any).toolResults ?? []) ?? [];
    const carDataResult = allToolResults.find((tr: any) => tr.toolName === "get_car_data" && tr.result?.found);
    const vehicleData: Record<string, unknown> | undefined = carDataResult?.result?.vehicleData;

    const groups = this.normalizeGroups(this.parseResponse(result.text));

    // Merge vehicle data with LLM-extracted fields using car-data mapper
    if (vehicleData) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const mapped = mapCarDataToFields(vehicleData as any);
      for (const g of groups) {
        g.vehicleData = vehicleData;
        // Merge: car data takes priority over LLM extraction
        g.category = mapped.category;
        g.extracted_fields = { ...(g.extracted_fields ?? {}), ...mapped.fields };
        // Remove fields from missing_fields that car data provided
        if (g.missing_fields) {
          const providedKeys = new Set(Object.keys(mapped.fields));
          g.missing_fields = g.missing_fields.filter(f => !providedKeys.has(f));
        }
      }
    }

    return { groups, tokensUsed: result.totalTokensUsed };
  }

  private parseResponse(text: string): unknown {
    // Strip markdown code blocks if present
    const cleaned = text.replace(/```json\s*([\s\S]*?)\s*```/g, "$1").trim();
    return JSON.parse(cleaned);
  }

  private normalizeGroups(data: unknown): ImageGroup[] {
    if (!data || (!Array.isArray(data) && typeof data !== "object")) return [];

    if (Array.isArray(data)) {
      if (
        data.length > 0 &&
        typeof data[0] === "object" &&
        "group_name" in data[0]
      ) {
        return data.map((item) => this.normalizeGroupItem(item));
      }
      return [];
    }

    const obj = data as Record<string, unknown>;
    if ("group_name" in obj) {
      return [this.normalizeGroupItem(obj)];
    }

    const values = Object.values(obj);
    if (values.length === 1 && Array.isArray(values[0])) {
      return this.normalizeGroups(values[0]);
    }

    return [];
  }

  private normalizeGroupItem(item: Record<string, unknown>): ImageGroup {
    return {
      group_name: item.group_name as string,
      description: item.description as string,
      images: item.images as string[],
      category: item.category as string | undefined,
      condition: item.condition as string | undefined,
      extracted_fields: item.extracted_fields as Record<string, string | number> | undefined,
      missing_fields: item.missing_fields as string[] | undefined,
      vehicleData: item.vehicleData as Record<string, unknown> | undefined,
    };
  }
}
