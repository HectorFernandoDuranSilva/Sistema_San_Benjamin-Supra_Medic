import { Type } from 'class-transformer';
import { IsArray, IsInt, IsNumber, IsOptional, Min, ValidateNested } from 'class-validator';
export class PurchaseItemDto { @Type(() => Number) @IsInt() productId!: number; @Type(() => Number) @IsNumber() @Min(0.001) quantity!: number; @Type(() => Number) @IsNumber() @Min(0) unitCost!: number; }
export class PurchaseDto { @IsOptional() @Type(() => Number) @IsInt() supplierId?: number; @IsArray() @ValidateNested({ each: true }) @Type(() => PurchaseItemDto) items!: PurchaseItemDto[]; }
