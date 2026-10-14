/** Laravel JsonResource responses are wrapped in `data`. */
export interface ApiResource<T> {
  data: T;
}

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface Credentials {
  email: string;
  password: string;
  remember?: boolean;
}

export interface Registration {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ValidationErrors {
  message: string;
  errors: Record<string, string[]>;
}

export type DocumentRole = 'owner' | 'editor' | 'viewer';
export type ShareRole = Exclude<DocumentRole, 'owner'>;
export type DocumentFilter = 'all' | 'owned' | 'shared';

export interface DocumentSummary {
  id: number;
  title: string;
  excerpt: string;
  role: DocumentRole;
  owner: { id: number; name: string };
  last_edited_by: string | null;
  collaborators_count: number;
  source_filename: string | null;
  created_at: string;
  updated_at: string;
}

export interface DocumentList {
  data: DocumentSummary[];
  meta: { counts: Record<DocumentFilter, number> };
}

export interface Collaborator extends User {
  role: ShareRole;
}

export interface DocumentDetail {
  id: number;
  title: string;
  content: string;
  version: number;
  role: DocumentRole;
  permissions: { edit: boolean; share: boolean; delete: boolean };
  owner: User;
  last_edited_by: string | null;
  collaborators: Collaborator[];
  source_filename: string | null;
  created_at: string;
  updated_at: string;
}

export interface DocumentPatch {
  version: number;
  title?: string;
  content?: string;
}

/** 409 body: someone else saved first. */
export interface SaveConflict {
  message: string;
  document: DocumentDetail;
}

export interface ConvertedFile {
  html: string;
  source_filename: string;
}

/* Realtime payloads */
export interface PresenceMember {
  id: number;
  name: string;
  role: DocumentRole;
}

export interface DocumentSavedEvent {
  document_id: number;
  version: number;
  title: string;
  saved_by: { id: number; name: string };
  saved_at: string;
}

export interface DocumentAccessEvent {
  document_id: number;
  title: string;
  role: ShareRole | null;
  change: 'granted' | 'updated' | 'revoked';
  changed_by: { id: number; name: string };
}
