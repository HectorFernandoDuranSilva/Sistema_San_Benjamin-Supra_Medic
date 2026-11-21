import { Body, Controller, Get, Param, ParseIntPipe, Post, Put, Request, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { QuotationDto } from './dto/quotation.dto';
import { QuotationsService } from './quotations.service';
@ApiTags('quotations') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('quotations')
export class QuotationsController {
  constructor(private readonly service: QuotationsService) {}
  @Get() findAll() { return this.service.findAll(); }
  @Post() create(@Body() dto: QuotationDto) { return this.service.create(dto); }
  @Put(':id') update(@Param('id', ParseIntPipe) id: number, @Body() dto: QuotationDto) { return this.service.update(id, dto); }
  @Post(':id/cancel') cancel(@Param('id', ParseIntPipe) id: number) { return this.service.cancel(id); }
  @Post(':id/convert') convert(@Param('id', ParseIntPipe) id: number, @Request() request: { user: { id: number } }) { return this.service.convert(id, request.user.id); }
}
