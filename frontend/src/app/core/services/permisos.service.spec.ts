import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { PermisosService } from './permisos.service';
import { ApiService } from './api.service';
import { environment } from '../../../environments/environment';
import { UserPermissionsResponse } from '../models/permisos';

describe('PermisosService', () => {
  let service: PermisosService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        PermisosService,
        ApiService,
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    });

    service = TestBed.inject(PermisosService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('loadPermissions()', () => {
    const mockResponse: UserPermissionsResponse = {
      global_role: 'EDITOR',
      permissions: [
        { modulo: 'atlas', nivel: 'escritura', departamento_id: 'dept-uuid-1' },
        { modulo: 'articulos', nivel: 'lectura' },
      ],
      departments: [
        { id: 'dept-uuid-1', nombre: 'Ciencia', role: 'EDITOR' },
      ],
    };

    it('should fetch from /permisos/user/permissions and cache result', () => {
      service.loadPermissions().subscribe((response) => {
        expect(response).toEqual(mockResponse);
        // Verify signal caches the response
        expect(service.userPermissions()).toEqual(mockResponse);
        expect(service.globalRole()).toBe('EDITOR');
        expect(service.departments().length).toBe(1);
        expect(service.departments()[0].nombre).toBe('Ciencia');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/permisos/user/permissions`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
    });

    it('should return empty departments when none assigned', () => {
      const noDeptResponse: UserPermissionsResponse = {
        global_role: 'USER',
        permissions: [],
        departments: [],
      };

      service.loadPermissions().subscribe((response) => {
        expect(response.global_role).toBe('USER');
        expect(service.departments()).toEqual([]);
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/permisos/user/permissions`);
      req.flush(noDeptResponse);
    });
  });

  describe('clearCache()', () => {
    it('should reset userPermissions signal', () => {
      // Set some state first
      const mockResponse: UserPermissionsResponse = {
        global_role: 'ADMIN',
        permissions: [],
        departments: [],
      };

      // Simulate a previous loadPermissions call
      service.loadPermissions().subscribe();
      const req = httpMock.expectOne(`${environment.apiUrl}/permisos/user/permissions`);
      req.flush(mockResponse);

      expect(service.userPermissions()).toBeTruthy();

      service.clearCache();
      expect(service.userPermissions()).toBeNull();
    });
  });
});
