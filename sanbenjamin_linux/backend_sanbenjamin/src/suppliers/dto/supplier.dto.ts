import { IsOptional, IsString, MaxLength } from 'class-validator';
export class SupplierDto {
  @IsString() @MaxLength(180) name!: string;
  @IsOptional() @IsString() @MaxLength(40) document?: string;
  @IsOptional() @IsString() @MaxLength(40) phone?: string;
  @IsOptional() @IsString() @MaxLength(250) address?: string;
}
