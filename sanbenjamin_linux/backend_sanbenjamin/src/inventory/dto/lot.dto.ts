import { IsDateString, IsInt, IsNumber, IsOptional, IsString, Min } from 'class-validator';
export class LotDto { @IsInt() productId!: number; @IsString() code!: string; @IsOptional() @IsDateString() expiryDate?: string; @IsNumber() @Min(0) quantity!: number; }
export class MovementDto { @IsInt() productId!: number; @IsOptional() @IsInt() lotId?: number; @IsNumber() quantity!: number; @IsString() type!: 'IN'|'OUT'|'ADJUSTMENT'; @IsOptional() @IsString() reference?: string; }
