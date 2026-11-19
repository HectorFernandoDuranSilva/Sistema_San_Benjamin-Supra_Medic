import { Type } from 'class-transformer';
import { IsInt, IsNumber, IsOptional, IsString, MaxLength, Min } from 'class-validator';

export class ProductDto {
  @IsString() @MaxLength(80) code!: string;
  @IsString() @MaxLength(180) name!: string;
  @IsOptional() @IsString() @MaxLength(40) unit?: string;
  @Type(() => Number) @IsNumber() @Min(0) salePrice!: number;
  @Type(() => Number) @IsNumber() @Min(0) purchaseCost!: number;
  @Type(() => Number) @IsNumber() @Min(0) minStock!: number;
  @Type(() => Number) @IsInt() categoryId!: number;
}
