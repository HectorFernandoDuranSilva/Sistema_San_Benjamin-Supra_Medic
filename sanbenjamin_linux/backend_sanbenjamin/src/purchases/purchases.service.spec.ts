import { BadRequestException } from '@nestjs/common';
import { PurchasesService } from './purchases.service';

describe('PurchasesService', () => {
  it('rejects a purchase without items', async () => {
    const service = new PurchasesService({} as never, {} as never);
    await expect(service.create({ items: [] }, 1)).rejects.toBeInstanceOf(BadRequestException);
  });
});
