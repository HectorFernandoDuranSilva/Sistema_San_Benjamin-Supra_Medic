import { IsEnum, IsInt, IsNumber, IsOptional, IsString, Min } from 'class-validator';
import { PaymentType } from '@prisma/client';
export class AccountPaymentDto { @IsInt() customerId!: number; @IsNumber() @Min(0.01) amount!: number; @IsEnum(PaymentType) type!: PaymentType; @IsOptional() @IsString() reference?: string; }
