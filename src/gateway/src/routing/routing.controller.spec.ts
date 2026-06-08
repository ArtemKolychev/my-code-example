import { Test, TestingModule } from '@nestjs/testing';
import { RoutingController } from './routing.controller';
import { HttpProxyService } from '../proxy/http-proxy.service';
import { RabbitMQPublisherService } from '../messaging/rabbitmq-publisher.service';
import { JwtGuard } from '../auth/jwt.guard';
import type { Request, Response } from 'express';

const mockProxy = { forward: jest.fn() };
const mockPublisher = { publish: jest.fn() };
const mockGuard = { canActivate: jest.fn(() => true) };

function makeReq(method: string, path: string): Request {
  return {
    method,
    params: { splat: path.replace(/^\//, '') },
    path,
    headers: {},
    body: {},
    search: '',
  } as unknown as Request;
}

function makeRes(): { status: jest.Mock; json: jest.Mock } & Partial<Response> {
  const res = { status: jest.fn(), json: jest.fn() };
  res.status.mockReturnValue(res);
  return res;
}

describe('RoutingController', () => {
  let controller: RoutingController;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [RoutingController],
      providers: [
        { provide: HttpProxyService, useValue: mockProxy },
        { provide: RabbitMQPublisherService, useValue: mockPublisher },
      ],
    })
      .overrideGuard(JwtGuard)
      .useValue(mockGuard)
      .compile();

    controller = module.get<RoutingController>(RoutingController);
    jest.clearAllMocks();
  });

  it('routes via:http POST /auth/register to proxy', async () => {
    mockProxy.forward.mockResolvedValue({ status: 202, data: { jobId: 'j1' } });
    const req = makeReq('POST', '/auth/register');
    const res = makeRes();

    await controller.handle(req, res as unknown as Response);

    expect(mockProxy.forward).toHaveBeenCalledTimes(1);
    expect(mockPublisher.publish).not.toHaveBeenCalled();
    expect(res.status).toHaveBeenCalledWith(202);
  });

  it('routes via:rabbitmq POST /users to publisher and returns 202', async () => {
    mockPublisher.publish.mockResolvedValue('job-abc');
    const req = makeReq('POST', '/users');
    const res = makeRes();

    await controller.handle(req, res as unknown as Response);

    expect(mockPublisher.publish).toHaveBeenCalledTimes(1);
    expect(mockProxy.forward).not.toHaveBeenCalled();
    expect(res.status).toHaveBeenCalledWith(202);
    expect(res.json).toHaveBeenCalledWith({ jobId: 'job-abc' });
  });

  it('returns 404 for unknown route', async () => {
    const req = makeReq('GET', '/unknown/path');
    const res = makeRes();

    await controller.handle(req, res as unknown as Response);

    expect(res.status).toHaveBeenCalledWith(404);
  });
});
