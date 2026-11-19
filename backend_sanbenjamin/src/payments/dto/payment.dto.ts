import { IsEnum, IsNumber, IsOptional, IsString, Min } from 'class-validator';
import { PaymentType } from '@prisma/client';
export class PaymentDto {
  @IsNumber() @Min(0.01) amount!: number;
  @IsEnum(PaymentType) type!: PaymentType;
  @IsOptional() @IsString() reference?: string;
}
