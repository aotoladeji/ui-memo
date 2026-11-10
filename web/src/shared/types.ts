export type Role = 'super_admin' | 'admin' | 'unit_head' | 'staff';
export type Classification = 'standard' | 'executive' | 'confidential';

export interface Me {
  id: number; name: string; email: string; role: Role; title?: string | null;
  unit_id: number | null; is_executive: boolean; can_send_global_broadcast: boolean;
}
export interface Reply { id: number; note_id: number; author_id: number; body: string; created_at: string }
export interface Note {
  id: number; memo_id: number; page_number: number; x: number; y: number; body: string;
  classification: Classification; requires_action: boolean; status: 'open' | 'resolved';
  author_id: number; assigned_to: number | null; color: string;
}
export interface Ann {
  id: string;                 // client id (stable); server id kept separately
  serverId?: number; page: number; tool: string; authorId: number;
  geometry: unknown; style?: unknown; classification?: Classification;
}
export interface AuthorInfo { id: number; name: string; title?: string | null; is_executive: boolean }
export interface PageDim { w: number; h: number }
export interface MemoView {
  memo: { id: number; ref_no: string; subject: string; priority: string; status: 'draft' | 'sent' | 'closed'; routing_type: 'internal_unit' | 'peer' | 'global'; created_by: number };
  route: { id: number; kind: 'origin' | 'forward'; depth: number };
  document: { url: string | null; mime: string; page_count: number; page_dims: PageDim[] | null; kind?: 'template' | 'file'; template?: any };
  annotations: { id: number; page_number: number; tool: string; geometry: unknown; style: unknown; classification: Classification; author_id: number }[];
  notes: Note[]; replies: Reply[]; authors: Record<string, AuthorInfo>;
}
export interface UserLite { id: number; name: string; title?: string | null }
export interface ForwardPayload {
  recipient_ids: number[]; message?: string; include_annotations: boolean;
  note_mode: 'all' | 'none'; hidden_classifications: Array<'executive' | 'confidential'>;
  rules: { target_type: 'sticky_note' | 'annotation'; target_id: number; effect: 'include' | 'exclude' }[];
}
