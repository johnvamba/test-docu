import { http } from '@/lib/http';
import type {
  ApiResource,
  ConvertedFile,
  DocumentDetail,
  DocumentFilter,
  DocumentList,
  DocumentPatch,
  ShareRole,
  User,
} from '@/types/api';

/** Typed wrappers for the document endpoints. Errors propagate as Axios errors. */
export const documentsApi = {
  async list(filter: DocumentFilter, q = ''): Promise<DocumentList> {
    const { data } = await http.get<DocumentList>('/api/documents', { params: { filter, q: q || undefined } });
    return data;
  },

  async create(title?: string): Promise<DocumentDetail> {
    const { data } = await http.post<ApiResource<DocumentDetail>>('/api/documents', { title });
    return data.data;
  },

  async get(id: number): Promise<DocumentDetail> {
    const { data } = await http.get<ApiResource<DocumentDetail>>(`/api/documents/${id}`);
    return data.data;
  },

  /** Throws a 409 Axios error (body: SaveConflict) when `version` is stale. */
  async update(id: number, patch: DocumentPatch): Promise<DocumentDetail> {
    const { data } = await http.patch<ApiResource<DocumentDetail>>(`/api/documents/${id}`, patch);
    return data.data;
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/api/documents/${id}`);
  },

  async importFile(file: File, onProgress?: (percent: number) => void): Promise<DocumentDetail> {
    const form = new FormData();
    form.append('file', file);
    const { data } = await http.post<ApiResource<DocumentDetail>>('/api/documents/import', form, {
      onUploadProgress: (e) => e.total && onProgress?.(Math.round((e.loaded / e.total) * 100)),
    });
    return data.data;
  },

  async convertFile(id: number, file: File): Promise<ConvertedFile> {
    const form = new FormData();
    form.append('file', file);
    const { data } = await http.post<ConvertedFile>(`/api/documents/${id}/import`, form);
    return data;
  },

  async share(id: number, email: string, role: ShareRole): Promise<DocumentDetail> {
    const { data } = await http.post<ApiResource<DocumentDetail>>(`/api/documents/${id}/shares`, { email, role });
    return data.data;
  },

  async changeRole(id: number, userId: number, role: ShareRole): Promise<DocumentDetail> {
    const { data } = await http.patch<ApiResource<DocumentDetail>>(`/api/documents/${id}/shares/${userId}`, { role });
    return data.data;
  },

  async unshare(id: number, userId: number): Promise<DocumentDetail> {
    const { data } = await http.delete<ApiResource<DocumentDetail>>(`/api/documents/${id}/shares/${userId}`);
    return data.data;
  },

  async searchUsers(q: string): Promise<User[]> {
    const { data } = await http.get<ApiResource<User[]>>('/api/users', { params: { q: q || undefined } });
    return data.data;
  },
};

export const SUPPORTED_IMPORTS = '.txt,.md,.markdown,.docx';
export const SUPPORTED_IMPORTS_LABEL = '.txt, .md or .docx, up to 5 MB';
export const MAX_IMPORT_BYTES = 5 * 1024 * 1024;

/** Client-side pre-check so obvious mistakes fail instantly; the server validates again. */
export function checkImportFile(file: File): string | null {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (!['txt', 'md', 'markdown', 'docx'].includes(ext)) return 'Unsupported file type. Upload a .txt, .md or .docx file.';
  if (file.size > MAX_IMPORT_BYTES) return 'Files can be up to 5 MB.';
  if (file.size === 0) return 'That file is empty.';
  return null;
}
