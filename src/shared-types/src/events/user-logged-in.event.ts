export interface UserLoggedInEvent {
  readonly jobId: string;
  readonly userId: string;
  readonly accessToken: string;
  readonly refreshToken: string;
  readonly expiresIn: number;
  readonly occurredAt: string; // ISO 8601
}
