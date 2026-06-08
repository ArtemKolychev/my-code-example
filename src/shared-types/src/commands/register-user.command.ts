export interface RegisterUserCommand {
  readonly jobId: string;
  readonly email: string;
  readonly password: string;
  readonly name: string;
}
