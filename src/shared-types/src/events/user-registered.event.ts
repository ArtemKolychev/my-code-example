export interface UserRegisteredEvent {
  readonly jobId: string;
  readonly userId: string;
  readonly email: string;
  readonly occurredAt: string; // ISO 8601
}
