import { Body, Controller, Post } from "@nestjs/common";
import { LlmChoiceService, ChoiceOption } from "./services/llm-choice.service";

interface ChoiceDto {
  prompt: string;
  options: ChoiceOption[];
  isMultiple?: boolean;
}

@Controller()
export class ChoiceController {
  constructor(private readonly llmChoiceService: LlmChoiceService) {}

  @Post("choose")
  async choose(@Body() dto: ChoiceDto): Promise<{ result: string[] }> {
    const result = await this.llmChoiceService.choose(
      dto.prompt,
      dto.options,
      dto.isMultiple,
    );
    return { result };
  }
}
