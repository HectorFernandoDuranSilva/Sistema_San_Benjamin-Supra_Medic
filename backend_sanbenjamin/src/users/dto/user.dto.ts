import { IsBoolean, IsEnum, IsOptional, IsString, MinLength } from 'class-validator';
import { UserRole } from '@prisma/client';
export class UserDto {
  @IsString() name!: string;
  @IsString() username!: string;
  @IsOptional() @IsString() @MinLength(6) password?: string;
  @IsEnum(UserRole) role!: UserRole;
  @IsOptional() @IsBoolean() active?: boolean;
}
