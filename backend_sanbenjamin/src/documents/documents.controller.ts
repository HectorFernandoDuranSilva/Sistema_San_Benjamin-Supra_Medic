import { Controller, Get, Header, Param, ParseIntPipe, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { DocumentsService } from './documents.service';
@ApiTags('documents') @ApiBearerAuth() @UseGuards(JwtAuthGuard) @Controller('documents')
export class DocumentsController { constructor(private readonly service: DocumentsService) {} @Get('sales/:id/receipt.pdf') @Header('Content-Type', 'application/pdf') @Header('Content-Disposition', 'attachment; filename="recibo-venta.pdf"') sale(@Param('id', ParseIntPipe) id: number) { return this.service.saleReceipt(id); } @Get('purchases/:id/receipt.pdf') @Header('Content-Type', 'application/pdf') @Header('Content-Disposition', 'attachment; filename="recibo-compra.pdf"') purchase(@Param('id', ParseIntPipe) id: number) { return this.service.purchaseReceipt(id); } }
