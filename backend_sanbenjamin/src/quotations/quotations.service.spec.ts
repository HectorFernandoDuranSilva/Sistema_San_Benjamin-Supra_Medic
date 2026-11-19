import { BadRequestException } from '@nestjs/common';
import { QuotationsService } from './quotations.service';
describe('QuotationsService', () => {
  it('rejects quotations without items', async () => { const service = new QuotationsService({} as never, {} as never); await expect(service.create({ items: [] })).rejects.toBeInstanceOf(BadRequestException); });
});
