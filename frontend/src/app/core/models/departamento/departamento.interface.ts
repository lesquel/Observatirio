import { Dataset } from '../dataset/dataset.interface';

export interface Departamento {
  id: string;
  nombre: string;
  codigo_interno: string;
  descripcion?: string;
  icono?: string;
  publico: boolean;
  powerbi_url?: string | null;
  powerbi_label?: string | null;
  created_at: string;
  updated_at: string;
  datasets?: Dataset[];
  datasets_count?: number;
}
