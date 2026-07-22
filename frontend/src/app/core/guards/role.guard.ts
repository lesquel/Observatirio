import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { PermisosService } from '../services/permisos.service';
import { UserRole } from '../models';

export const adminGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.isAdmin()) {
    return true;
  }

  // Redirigir a dashboard si no es admin
  router.navigate(['/admin/dashboard']);
  return false;
};

/**
 * Guard configurable que verifica si el usuario tiene alguno de los roles especificados
 * o permisos equivalentes a nivel de módulo/departamento.
 */
export function roleGuard(...allowedRoles: UserRole[]): CanActivateFn {
  return () => {
    const authService = inject(AuthService);
    const permisosService = inject(PermisosService);
    const router = inject(Router);

    if (authService.hasAnyRole(allowedRoles)) {
      return true;
    }

    const user = authService.user();
    if (user && allowedRoles.some((r) => r === 'EDITOR' || r === 'ADMIN')) {
      if (
        permisosService.puedeEditar(user.id, 'atlas') ||
        permisosService.esAdmin(user.id, 'atlas') ||
        permisosService.puedeEditar(user.id, 'articulos') ||
        permisosService.esAdmin(user.id, 'articulos') ||
        permisosService.puedeEditar(user.id, 'reportes') ||
        permisosService.esAdmin(user.id, 'reportes') ||
        permisosService.puedeEditar(user.id, 'observatorios') ||
        permisosService.esAdmin(user.id, 'observatorios') ||
        permisosService.departments().some((d) => d.role === 'EDITOR' || d.role === 'ADMIN')
      ) {
        return true;
      }
    }

    // Redirigir según el rol del usuario
    const userRole = authService.userRole();
    if (!userRole) {
      router.navigate(['/auth/login']);
    } else {
      router.navigate(['/admin/dashboard']);
    }

    return false;
  };
}
