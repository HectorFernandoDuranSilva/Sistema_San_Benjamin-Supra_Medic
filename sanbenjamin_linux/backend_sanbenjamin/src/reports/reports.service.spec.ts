import { ReportsService } from './reports.service';
describe('ReportsService', () => {
  it('calculates the monthly result', async () => { const prisma = { sale: { aggregate: jest.fn().mockResolvedValue({ _sum: { total: 100 } }) }, purchase: { aggregate: jest.fn().mockResolvedValue({ _sum: { total: 40 } }) }, operationalExpense: { aggregate: jest.fn().mockResolvedValue({ _sum: { amount: 10 } }) } }; const service = new ReportsService(prisma as never); await expect(service.monthly(2026, 1)).resolves.toMatchObject({ sales: 100, purchases: 40, expenses: 10, result: 50 }); });
});
