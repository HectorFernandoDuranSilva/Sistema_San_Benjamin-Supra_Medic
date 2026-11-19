import { BadRequestException } from '@nestjs/common';
import { PaymentsService } from './payments.service';

describe('PaymentsService', () => {
  it('rejects a payment greater than the sale total', async () => {
    const prisma = {
      sale: { findUnique: jest.fn().mockResolvedValue({ id: 1, total: 100 }) },
      payment: { aggregate: jest.fn().mockResolvedValue({ _sum: { amount: 80 } }) },
    };
    const service = new PaymentsService(prisma as never, { record: jest.fn() } as never);
    await expect(service.forSale(1, { amount: 30, type: 'CASH' } as never)).rejects.toBeInstanceOf(BadRequestException);
  });
});
