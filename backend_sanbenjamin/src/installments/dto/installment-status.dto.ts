import { IsEnum } from 'class-validator';
import { InstallmentStatus } from '@prisma/client';
export class InstallmentStatusDto { @IsEnum(InstallmentStatus) status!: InstallmentStatus; }
