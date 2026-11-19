import { Type } from 'class-transformer';
import { IsArray, IsInt, IsNumber, IsOptional, Min, ValidateNested } from 'class-validator';
export class SaleItemDto { @Type(() => Number) @IsInt() productId!: number; @Type(() => Number) @IsNumber() @Min(0.001) quantity!: number; @Type(() => Number) @IsNumber() @Min(0) unitPrice!: number; }
export class SaleDto { @IsOptional() @Type(() => Number) @IsInt() customerId?: number; @IsArray() @ValidateNested({ each: true }) @Type(() => SaleItemDto) items!: SaleItemDto[]; }
