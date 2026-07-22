import { Injectable, inject, signal, computed } from '@angular/core';
import { Observable, of, tap, catchError, map } from 'rxjs';
import { PermisosApiService } from './permisos-api.service';
import { ModuloPermiso, NivelPermiso, PermisoConfig, UserPermissionsResponse } from '../models/permisos';

/**
 * Servicio unificado de permisos.
 * Todos los módulos se sincronizan con el backend.
 */
@Injectable({
  providedIn: 'root',
})
export class PermisosService {
  private readonly permisosApi = inject(PermisosApiService);
  private cache = new Map<number, PermisoConfig[]>();

  // UserPermissions cache (session-scoped, FRT-02)
  private readonly userPermissionsSignal = signal<UserPermissionsResponse | null>(null);
  readonly userPermissions = computed(() => this.userPermissionsSignal());
  readonly globalRole = computed(() => this.userPermissionsSignal()?.global_role ?? null);
  readonly departments = computed(() => this.userPermissionsSignal()?.departments ?? []);

  getUserPermisos(userId: number): PermisoConfig[] {
    return this.cache.get(userId) ?? [];
  }

  /**
   * Obtiene el nivel de permiso de un usuario específico desde el caché administrado.
   */
  getUserNivel(userId: number, modulo: ModuloPermiso, departamentoId?: string | null): NivelPermiso {
    const permisos = this.cache.get(userId) ?? [];

    if (departamentoId) {
      const match = permisos.find(
        (p) => p.modulo === modulo && p.departamento_id === departamentoId
      );
      if (match && match.nivel !== 'ninguno') return match.nivel;
    }

    const generic = permisos.find(
      (p) => p.modulo === modulo && !p.departamento_id
    );
    if (generic && generic.nivel !== 'ninguno') return generic.nivel;

    const any = permisos.find((p) => p.modulo === modulo);
    return any?.nivel ?? 'ninguno';
  }

  getNivel(userId: number, modulo: ModuloPermiso, departamentoId?: string | null): NivelPermiso {
    if (this.globalRole() === 'ADMIN') {
      return 'admin';
    }

    const permisos = this.userPermissionsSignal()?.permissions ?? (this.cache.get(userId) ?? []);

    if (departamentoId) {
      const match = permisos.find(
        (p) => p.modulo === modulo && p.departamento_id === departamentoId
      );
      if (match && match.nivel !== 'ninguno') return match.nivel;
    }

    const generic = permisos.find(
      (p) => p.modulo === modulo && (!p.departamento_id)
    );
    if (generic && generic.nivel !== 'ninguno') return generic.nivel;

    if (departamentoId) {
      const dept = this.departments().find((d) => d.id === departamentoId);
      if (dept?.role) {
        return this.deptRoleToNivel(dept.role);
      }
    }

    const any = permisos.find((p) => p.modulo === modulo);
    return any?.nivel ?? 'ninguno';
  }

  private deptRoleToNivel(role: string): NivelPermiso {
    switch (role) {
      case 'ADMIN': return 'admin';
      case 'EDITOR': return 'escritura';
      case 'LECTOR': return 'lectura';
      default: return 'ninguno';
    }
  }

  hasMinNivel(
    userId: number,
    modulo: ModuloPermiso,
    minNivel: NivelPermiso,
    departamentoId?: string | null
  ): boolean {
    return this.nivelWeight(this.getNivel(userId, modulo, departamentoId)) >= this.nivelWeight(minNivel);
  }

  puedeVer(userId: number, modulo: ModuloPermiso, departamentoId?: string | null): boolean {
    return this.hasMinNivel(userId, modulo, 'lectura', departamentoId);
  }

  puedeEditar(userId: number, modulo: ModuloPermiso, departamentoId?: string | null): boolean {
    return this.hasMinNivel(userId, modulo, 'escritura', departamentoId);
  }

  esAdmin(userId: number, modulo: ModuloPermiso, departamentoId?: string | null): boolean {
    return this.hasMinNivel(userId, modulo, 'admin', departamentoId);
  }

  saveUserPermisos(userId: number, permisos: PermisoConfig[]): Observable<PermisoConfig[]> {
    return this.permisosApi.saveUserPermisos(userId, permisos).pipe(
      map((response) => {
        const saved = (response.permisos ?? permisos).filter((p) => p.nivel !== 'ninguno');
        this.cache.set(userId, saved);
        return saved;
      })
    );
  }

  syncFromBackend(userId: number): Observable<PermisoConfig[]> {
    return this.permisosApi.getUserPermisos(userId).pipe(
      tap((backendPermisos) => this.cache.set(userId, backendPermisos ?? [])),
      catchError(() => {
        console.warn('No se pudieron cargar permisos del backend.');
        return of(this.cache.get(userId) ?? []);
      })
    );
  }

  /** Carga el DTO unificado de permisos desde /api/user/permissions (FRT-02) */
  loadPermissions(): Observable<UserPermissionsResponse> {
    return this.permisosApi.getUserPermissions().pipe(
      tap((response) => this.userPermissionsSignal.set(response))
    );
  }

  clearCache(userId?: number): void {
    if (userId !== undefined) {
      this.cache.delete(userId);
    } else {
      this.cache.clear();
    }
    this.userPermissionsSignal.set(null);
  }

  private nivelWeight(nivel: NivelPermiso): number {
    const weights: Record<NivelPermiso, number> = {
      ninguno: 0,
      lectura: 1,
      escritura: 2,
      admin: 3,
    };
    return weights[nivel] ?? 0;
  }
}
