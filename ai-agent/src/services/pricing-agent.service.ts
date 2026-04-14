import { Injectable, Logger } from "@nestjs/common";
import { tool } from "ai";
import { openai } from "@ai-sdk/openai";
import { loggedGenerateText } from "../logging/logged-generate-text";
import { LinkupClient } from "linkup-sdk";
import { z } from "zod";

@Injectable()
export class PricingAgentService {
  private readonly logger = new Logger(PricingAgentService.name);

  async suggestPrice(
    title: string,
    description: string,
    condition?: string,
  ): Promise<{
    price: number;
    reasoning: string;
    sources: { name: string; url: string }[];
    tokensUsed: number;
  }> {
    this.logger.log("Suggesting price for: %s", title);

    const sources: { name: string; url: string }[] = [];

    const { text, totalTokensUsed } = await loggedGenerateText({
      model: openai("gpt-5-mini"),
      maxSteps: 8,
      tools: {
        linkup_search: tool({
          description:
            "Search for similar listings on Czech marketplaces (sbazar.cz, bazos.cz, etc.)",
          parameters: z.object({
            query: z.string().describe("Search query for similar items"),
            depth: z
              .enum(["standard", "deep"])
              .describe("Search depth: 'standard' for most queries, 'deep' for detailed research"),
          }),
          execute: async ({ query, depth }) => {
            this.logger.log("Linkup search: query=%s, depth=%s", query, depth);
            const linkup = new LinkupClient({
              apiKey: process.env.LINKUP_API_KEY,
            });
            const res = await linkup.search({
              query,
              depth,
              outputType: "searchResults",
            });

            const items = res.results.flatMap((r) =>
              "content" in r
                ? [{ name: r.name, url: r.url, content: r.content }]
                : [],
            );

            // Collect top 3 unique sources per search call, max 8 total
            let added = 0;
            for (const item of items) {
              if (added >= 3 || sources.length >= 8) break;
              if (item.url && !sources.some((s) => s.url === item.url)) {
                sources.push({ name: item.name, url: item.url });
                added++;
              }
            }

            return items;
          },
        }),
      },
      system: `You are a pricing expert for Czech classifieds (sbazar.cz, bazos.cz).
Your goal is to suggest a realistic second-hand selling price in CZK.

Follow this process STRICTLY:
1. Extract the exact brand name and model number from the article title/description (e.g. "Mean Well S-400-24", "Samsung Galaxy S21").
2. Search for that EXACT model name + item type + "cena" to find new retail price in Czech e-shops. ALWAYS include the item type/category in the query (e.g. "Shark helma Special Edition cena", not just "Shark Special Edition cena"). This prevents confusing brands that have multiple product lines.
3. Search for used listings of the SAME model on Czech classifieds, also including item type: "[brand] [model] [item type] bazar" or "[brand] [model] [item type] sbazar.cz".
4. Based on found new price and condition: new=80-90%, good condition=40-60%, worn=20-40%, damaged=10-20% of new retail price.
5. If the new retail price is below 1000 CZK, the used price MUST be lower than the new price.

Return your final answer as JSON: { "price": <number>, "reasoning": "<explanation: new price found X CZK, condition Y, therefore Z CZK>" }`,
      prompt: `Article: "${title}"\nDescription: "${description}"\n${condition ? `Stav zadaný uživatelem: "${condition}"\n` : ""}\nStep 1: identify the exact model. Step 2: search for its new retail price. Step 3: search for used listings. Step 4: calculate fair used price based on the provided condition.`,
    }, "pricing-agent");

    this.logger.log("Price suggestion raw response: %s", text);

    try {
      const parsed = JSON.parse(text);
      return {
        price: Number(parsed.price) || 0,
        reasoning: String(parsed.reasoning || ""),
        sources,
        tokensUsed: totalTokensUsed,
      };
    } catch {
      this.logger.warn("Failed to parse price response, extracting manually");
      const priceMatch = text.match(/"price"\s*:\s*(\d+)/);
      return {
        price: priceMatch ? Number(priceMatch[1]) : 0,
        reasoning: text,
        sources,
        tokensUsed: totalTokensUsed,
      };
    }
  }
}
