import { BadRequestException } from '@nestjs/common';
import { InventoryService } from './inventory.service';
describe('InventoryService', () => {
  it('rejects a zero adjustment', async () => { const service = new InventoryService({} as never); await expect(service.adjust({ productId: 1, type: 'ADJUSTMENT', quantity: 0 })).rejects.toBeInstanceOf(BadRequestException); });
  it('rejects an adjustment that leaves negative stock', async () => { const tx = { product: { findUnique: jest.fn().mockResolvedValue({ id: 1, stock: 2 }) } }; const prisma = { $transaction: jest.fn(async (callback: (client: typeof tx) => unknown) => callback(tx)) }; const service = new InventoryService(prisma as never); await expect(service.adjust({ productId: 1, type: 'OUT', quantity: 3 })).rejects.toThrow('stock negativo'); });
});
