import { Type } from 'class-transformer';
import { IsArray, IsInt, IsNumber, IsOptional, Min, ValidateNested } from 'class-validator';
export class QuotationItemDto { @Type(() => Number) @IsInt() productId!: number; @Type(() => Number) @IsNumber() @Min(0.001) quantity!: number; @Type(() => Number) @IsNumber() @Min(0) unitPrice!: number; }
export class QuotationDto { @IsOptional() @Type(() => Number) @IsInt() customerId?: number; @IsArray() @ValidateNested({ each: true }) @Type(() => QuotationItemDto) items!: QuotationItemDto[]; }
