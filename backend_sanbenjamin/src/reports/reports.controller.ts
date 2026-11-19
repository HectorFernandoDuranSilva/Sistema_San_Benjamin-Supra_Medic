import { Controller, Get, Header, Query, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { ReportsService } from './reports.service';
import { Response } from 'express';

@ApiTags('reports')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('reports')
export class ReportsController {
  constructor(private readonly reports: ReportsService) {}
  @Get('summary') summary() { return this.reports.summary(); }
  @Get('monthly') monthly(@Query('year') year = String(new Date().getFullYear()), @Query('month') month = String(new Date().getMonth() + 1)) { return this.reports.monthly(Number(year), Number(month)); }
  @Get('sales.csv') @Header('Content-Type', 'text/csv; charset=utf-8') @Header('Content-Disposition', 'attachment; filename="ventas.csv"') salesCsv() { return this.reports.salesCsv(); }
  @Get('sales.xlsx') @Header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') @Header('Content-Disposition', 'attachment; filename="ventas.xlsx"') salesExcel() { return this.reports.salesExcel(); }
  @Get('sales.pdf') @Header('Content-Type', 'application/pdf') @Header('Content-Disposition', 'attachment; filename="ventas.pdf"') salesPdf() { return this.reports.salesPdf(); }
}
