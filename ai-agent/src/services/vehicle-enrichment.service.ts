import { Injectable, Logger } from "@nestjs/common";
import { CarDataClient, CarNotFoundError } from "get-car-data";
import type { CarData } from "get-car-data";

export type { CarData };

/**
 * Mapped vehicle data saved to article.meta.
 * Semantic fields for clicker adapters + full raw CarData from overeniauta.cz.
 */
export interface VehicleData {
  // Semantic fields used by clicker adapters (motoinzerce, etc.)
  brand?: string;
  model?: string;
  year?: number;
  displacement_cc?: number;
  power_kw?: number;
  vin?: string;
  category?: string; // EU category: L3e (motorcycle), M1 (car), etc.
  fuel?: string;
  color?: string;
  mileage_km?: number; // from last inspection
  status?: string; // e.g. "PROVOZOVANÉ"
  // Full raw data from overeniauta.cz — all fields persisted
  carData?: CarData;
}

/**
 * Enriches vehicle data by VIN or SPZ (Czech license plate) via overeniauta.cz.
 *
 * Uses the get-car-data library (cz-cars-parser) — no API key required.
 * Supports: VIN (17-char) and SPZ (Czech/Slovak license plate).
 *
 * The result is intended to be persisted in article.meta so subsequent
 * calls do not need to re-fetch (article.meta acts as the cache).
 */
@Injectable()
export class VehicleEnrichmentService {
  private readonly logger = new Logger(VehicleEnrichmentService.name);
  private readonly client = new CarDataClient({ cache: false });

  /**
   * Enrich by VIN (17-char vehicle identification number).
   */
  async enrichByVin(vin: string): Promise<VehicleData | null> {
    this.logger.log("Fetching vehicle data by VIN: %s", vin);
    return this.fetchAndMap(vin);
  }

  /**
   * Enrich by SPZ (Czech/Slovak license plate, e.g. "2BV6683").
   */
  async enrichBySpz(spz: string): Promise<VehicleData | null> {
    const normalized = spz.replace(/\s/g, "").toUpperCase();
    this.logger.log("Fetching vehicle data by SPZ: %s", normalized);
    return this.fetchAndMap(normalized);
  }

  private async fetchAndMap(vinOrSpz: string): Promise<VehicleData | null> {
    let carData: CarData;

    try {
      carData = await this.client.getByVin(vinOrSpz);
    } catch (err) {
      if (err instanceof CarNotFoundError) {
        this.logger.log("Vehicle not found: %s", vinOrSpz);
        return null;
      }
      this.logger.error(
        "Vehicle lookup failed for %s: %s",
        vinOrSpz,
        err instanceof Error ? err.message : String(err),
      );
      return null;
    }

    this.logger.log("overeniauta.cz response: %o", carData);

    const result: VehicleData = {
      brand: carData.make || undefined,
      model: carData.model || undefined,
      year: carData.year || undefined,
      displacement_cc: carData.engine.displacementCc || undefined,
      power_kw: carData.engine.powerKw || undefined,
      vin: carData.vin || undefined,
      category: carData.body.category || undefined,
      fuel: carData.engine.fuelType || undefined,
      color: carData.body.colorName || undefined,
      mileage_km: carData.inspection?.mileageKm || undefined,
      status: carData.status || undefined,
      // Full raw data for complete persistence
      carData,
    };

    this.logger.log("Mapped vehicle data: %o", result);
    return result;
  }
}
