import { IsEnum } from 'class-validator';
import { PaymentStatus } from '@prisma/client';
export class PaymentStatusDto { @IsEnum(PaymentStatus) status!: PaymentStatus; }
