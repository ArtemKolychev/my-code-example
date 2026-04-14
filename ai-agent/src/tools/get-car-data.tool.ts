import { tool } from "ai";
import { z } from "zod";
import type { VehicleEnrichmentService } from "../services/vehicle-enrichment.service";

export function createGetCarDataTool(vehicleEnrichmentService: VehicleEnrichmentService) {
  return tool({
    description:
      "Fetch vehicle technical data from Czech/Slovak car registry by license plate (SPZ) or VIN. " +
      "Call this as soon as you have a license plate or VIN — before writing any description.",
    parameters: z.object({
      identifier: z.string().describe("SPZ license plate (e.g. '2BV6683') or 17-char VIN"),
    }),
    execute: async ({ identifier }) => {
      const data = await vehicleEnrichmentService.enrichByVin(identifier);
      if (!data) return { found: false };
      return { found: true, vehicleData: data };
    },
  });
}
