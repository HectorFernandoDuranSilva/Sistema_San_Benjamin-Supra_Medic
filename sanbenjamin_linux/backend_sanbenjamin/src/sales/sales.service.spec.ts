import { BadRequestException } from '@nestjs/common';
import { SalesService } from './sales.service';

describe('SalesService', () => {
  it('rejects a sale without items', async () => {
    const service = new SalesService({} as never, {} as never);
    await expect(service.create({ items: [] }, 1)).rejects.toBeInstanceOf(BadRequestException);
  });

  it('rejects a sale when stock is insufficient', async () => {
    const tx = {
      product: { findUnique: jest.fn().mockResolvedValue({ id: 1, name: 'Producto', active: true, stock: 2 }) },
    };
    const prisma = { $transaction: jest.fn(async (callback: (client: typeof tx) => unknown) => callback(tx)) };
    const service = new SalesService(prisma as never, { record: jest.fn() } as never);
    await expect(service.create({ items: [{ productId: 1, quantity: 3, unitPrice: 10 }] }, 1)).rejects.toThrow('Stock insuficiente');
  });
});
