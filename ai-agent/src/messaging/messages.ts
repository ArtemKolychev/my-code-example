// Commands: BE → AI-Agent
export type GroupImagesCommand = {
  jobId: string;
  batchId: number;
  articleImages: { path: string; url: string }[];
  vehicleIdentifier?: string; // pre-known SPZ/VIN provided by user after ask_user
  condition?: string; // condition label in Czech, e.g. "Dobré"
};

export type SuggestPriceCommand = {
  jobId: string;
  articleId: number;
  title: string;
  description: string;
  condition?: string; // condition label in Czech, e.g. "Dobré"
};

export type EnrichVehicleCommand = {
  jobId: string;
  articleId: number;
  vin?: string;
  spz?: string; // Czech/Slovak license plate (SPZ)
  articleImages?: { path: string; url: string }[];
};

// Events: AI-Agent → BE (reuse same clicker.events exchange)
export type ProgressEvent = {
  type: "progress";
  jobId: string;
  step: string;
  stepIndex: number;
  totalSteps: number;
};

export type CompletedEvent = {
  type: "completed";
  jobId: string;
  result: Record<string, unknown>;
};

export type FailedEvent = {
  type: "failed";
  jobId: string;
  step: string;
  error: string;
};

export type NeedsInputEvent = {
  type: "needs_input";
  jobId: string;
  articleId: number | null;
  inputType: string;
  inputPrompt: string;
  imageUrls?: string[];
};
