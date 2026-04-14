import type { CarData } from 'get-car-data';
import { Category, FuelType, BodyType } from '@bazar-ai/shared-types';

export interface CarDataMapResult {
  category: Category;
  fields: Record<string, string | number>;
}

// --- Category mapping (EU vehicle classification) ---

const CATEGORY_MAP: Record<string, Category> = {
  M1: Category.Car,
  M1G: Category.Car,
  N1: Category.Truck,
  N2: Category.Truck,
  N3: Category.Truck,
  L1e: Category.Motorcycle,
  L2e: Category.Motorcycle,
  L3e: Category.Motorcycle,
  L4e: Category.Motorcycle,
  L5e: Category.Motorcycle,
  L6e: Category.Motorcycle,
  L7e: Category.Motorcycle,
};

function mapCategory(bodyCategory: string): Category {
  return CATEGORY_MAP[bodyCategory] ?? Category.Car;
}

// --- FuelType mapping (Czech abbreviations) ---

const FUEL_TYPE_MAP: Record<string, FuelType> = {
  ba: FuelType.Petrol,
  b: FuelType.Petrol,
  nm: FuelType.Diesel,
  n: FuelType.Diesel,
  el: FuelType.Electric,
  e: FuelType.Electric,
  lpg: FuelType.LPG,
};

function mapFuelType(carData: CarData): FuelType | undefined {
  if (carData.isElectric) return FuelType.Electric;
  if (carData.isHybrid) return FuelType.Hybrid;

  const raw = carData.engine?.fuelType;
  if (!raw) return undefined;

  return FUEL_TYPE_MAP[raw.toLowerCase().trim()];
}

// --- BodyType mapping (Czech terms) ---

const BODY_TYPE_MAP: Record<string, BodyType> = {
  sedan: BodyType.Sedan,
  hatchback: BodyType.Hatchback,
  kombi: BodyType.Combi,
  combi: BodyType.Combi,
  suv: BodyType.SUV,
  'terénní': BodyType.SUV,
  'terenni': BodyType.SUV,
  mpv: BodyType.MPV,
  minivan: BodyType.MPV,
  kabriolet: BodyType.Cabrio,
  kabrio: BodyType.Cabrio,
  pickup: BodyType.Pickup,
  'dodávka': BodyType.Van,
  'dodavka': BodyType.Van,
  van: BodyType.Van,
};

function mapBodyType(bodyType: string): BodyType | undefined {
  if (!bodyType) return undefined;
  return BODY_TYPE_MAP[bodyType.toLowerCase().trim()];
}

// --- Main mapper ---

export function mapCarDataToFields(carData: CarData): CarDataMapResult {
  const category = mapCategory(carData.body?.category ?? '');
  const fields: Record<string, string | number> = {};

  // Enum fields (only included if mapped successfully)
  const fuelType = mapFuelType(carData);
  if (fuelType) fields.fuel_type = fuelType;

  const bodyType = mapBodyType(carData.body?.type ?? '');
  if (bodyType) fields.body_type = bodyType;

  // Direct field mappings — only include truthy values
  const directMappings: Array<[string, string | number | undefined]> = [
    ['brand', carData.make],
    ['model', carData.model],
    ['year', carData.year],
    ['displacement_ccm', carData.engine?.displacementCc],
    ['power_kw', carData.engine?.powerKw],
    ['vin', carData.vin],
    ['color', carData.body?.colorName],
    ['mileage_km', carData.inspection?.mileageKm],
  ];

  for (const [key, value] of directMappings) {
    if (value) {
      fields[key] = value;
    }
  }

  return { category, fields };
}
