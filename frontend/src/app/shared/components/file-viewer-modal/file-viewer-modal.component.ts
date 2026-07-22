import { CommonModule } from '@angular/common';
import { Component, EventEmitter, inject, Input, Output, signal } from '@angular/core';
import { Router } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

export interface ArchivoVisor {
  url: string;
  nombre: string;
  esPreview?: boolean;
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
  private readonly router = inject(Router);

  private _archivo: ArchivoVisor | null = null;
  safeUrl: SafeResourceUrl | null = null;
  iframeLoaded = signal(false);

  @Input()
  set archivo(value: ArchivoVisor | null) {
    this._archivo = value;
    this.iframeLoaded.set(false);
    
    if (value) {
      let finalUrl = value.url;
      if (value.esPreview) {
        // Append PDF page 1 parameters if not already present
        const hashIndex = finalUrl.indexOf('#');
        if (hashIndex === -1) {
          finalUrl += '#page=1&toolbar=0&navpanes=0&scrollbar=0';
        }
      }
      this.safeUrl = this.sanitizer.bypassSecurityTrustResourceUrl(finalUrl);
    } else {
      this.safeUrl = null;
    }
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

  onSuscribirse(): void {
    this.onCerrar();
    this.router.navigate(['/auth/login']);
  }
}
