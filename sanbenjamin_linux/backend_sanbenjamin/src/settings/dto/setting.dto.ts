import { IsString } from 'class-validator';
export class SettingDto { @IsString() key!: string; @IsString() value!: string; }
