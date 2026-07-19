import { CommonModule } from '@angular/common';
import { Component, EventEmitter, inject, Input, Output, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

export interface ArchivoVisor {
  url: string;
  nombre: string;
}

/**
 * Visor embebido de archivos (PDF, PowerBI, etc.) en un iframe dentro de la misma página.
 * Se usa en vez de abrir un link externo para que la PWA instalada no salga de la app
 * al navegar a otro origen (comportamiento estándar de display: standalone).
 */
@Component({
  selector: 'app-file-viewer-modal',
  standalone: true,
  imports: [CommonModule, MatButtonModule, MatIconModule, MatProgressSpinnerModule],
  templateUrl: './file-viewer-modal.component.html',
  styleUrl: './file-viewer-modal.component.scss',
})
export class FileViewerModalComponent {
  private readonly sanitizer = inject(DomSanitizer);

  private _archivo: ArchivoVisor | null = null;
  safeUrl: SafeResourceUrl | null = null;
  iframeLoaded = signal(false);

  @Input()
  set archivo(value: ArchivoVisor | null) {
    this._archivo = value;
    this.iframeLoaded.set(false);
    this.safeUrl = value ? this.sanitizer.bypassSecurityTrustResourceUrl(value.url) : null;
  }
  get archivo(): ArchivoVisor | null {
    return this._archivo;
  }

  @Output() cerrar = new EventEmitter<void>();

  onIframeLoad(): void {
    this.iframeLoaded.set(true);
  }

  onCerrar(): void {
    this.cerrar.emit();
  }
}
