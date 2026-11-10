import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Building2, Upload, X } from 'lucide-react';
import { api, errorText } from '@/shared/api';
import { useDocumentTitle } from '@/shared/useDocumentTitle';
import { useAuth } from '@/features/auth/authStore';

interface Unit { id: number; name: string; code: string }
interface Dept { id: number; name: string; code: string; units: Unit[]; has_logo?: boolean }
interface Fac { id: number; name: string; code: string; departments: Dept[] }

export default function OrgPage() {
  useDocumentTitle('Organisation');
  const me = useAuth((s) => s.me);
  const isSuperAdmin = me?.role === 'super_admin';
  const [showAddDept, setShowAddDept] = useState(false);
  const { data } = useQuery({ queryKey: ['org'], queryFn: async () => (await api.get<Fac[]>('/admin/org')).data });
  
  return (
    <div className="mx-auto max-w-3xl p-4 sm:p-6">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Organisation</h1>
          <p className="text-sm text-ink-soft">Institution → Faculties → Departments → Units.</p>
        </div>
        {isSuperAdmin && (
          <button className="btn btn-primary flex items-center gap-2" onClick={() => setShowAddDept(true)}>
            <Building2 size={16} /> Add Department
          </button>
        )}
      </div>

      <ul className="space-y-3">
        {data?.map((f) => (
          <li key={f.id} className="card p-4">
            <h2 className="font-bold">{f.name} <span className="font-normal text-ink-soft">({f.code})</span></h2>
            <ul className="mt-2 space-y-2 border-l-2 border-brand-200 pl-4">
              {f.departments.map((d) => (
                <li key={d.id}>
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <p className="font-bold">
                        {d.name} 
                        {d.has_logo && <span className="ml-2 text-xs text-green-600">● Has logo</span>}
                      </p>
                      <ul className="mt-1 flex flex-wrap gap-2">
                        {d.units.map((u) => (
                          <li key={u.id} className="rounded-full bg-brand-100 px-3 py-1 text-sm">{u.name}</li>
                        ))}
                      </ul>
                    </div>
                    {isSuperAdmin && <UploadLogoButton deptId={d.id} hasLogo={d.has_logo} />}
                  </div>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>

      {showAddDept && (
        <AddDepartmentDialog 
          faculties={data || []} 
          onClose={() => setShowAddDept(false)} 
        />
      )}
    </div>
  );
}

function UploadLogoButton({ deptId, hasLogo }: { deptId: number; hasLogo?: boolean }) {
  const queryClient = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [err, setErr] = useState('');

  const upload = useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData();
      fd.append('logo', file);
      await api.post(`/admin/departments/${deptId}/logo`, fd);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['org'] });
      setFile(null);
      setErr('');
    },
    onError: (e) => setErr(errorText(e))
  });

  return (
    <div className="flex items-center gap-2">
      <input
        type="file"
        accept="image/*"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) {
            if (f.size > 1024 * 1024) {
              setErr('File must be under 1 MB');
              return;
            }
            setFile(f);
            setErr('');
          }
        }}
        className="hidden"
        id={`logo-${deptId}`}
      />
      <label
        htmlFor={`logo-${deptId}`}
        className="btn btn-ghost flex cursor-pointer items-center gap-1 text-xs"
      >
        <Upload size={14} /> {hasLogo ? 'Change Logo' : 'Upload Logo'}
      </label>
      {file && (
        <>
          <span className="text-xs text-ink-soft">{file.name}</span>
          <button
            className="btn btn-primary px-2 text-xs"
            onClick={() => upload.mutate(file)}
            disabled={upload.isPending}
          >
            {upload.isPending ? 'Uploading...' : 'Save'}
          </button>
          <button
            className="btn btn-ghost px-2 text-xs"
            onClick={() => setFile(null)}
          >
            <X size={14} />
          </button>
        </>
      )}
      {err && <p className="text-xs text-red-700">{err}</p>}
    </div>
  );
}

function AddDepartmentDialog({ faculties, onClose }: { faculties: Fac[]; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [facultyId, setFacultyId] = useState(faculties[0]?.id || 0);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [logo, setLogo] = useState<File | null>(null);
  const [err, setErr] = useState('');

  const create = useMutation({
    mutationFn: async () => {
      const fd = new FormData();
      fd.append('faculty_id', String(facultyId));
      fd.append('name', name);
      fd.append('code', code);
      if (logo) fd.append('logo', logo);
      await api.post('/admin/departments', fd);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['org'] });
      onClose();
    },
    onError: (e) => setErr(errorText(e))
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" onClick={onClose}>
      <div className="card w-full max-w-md bg-white p-6" onClick={(e) => e.stopPropagation()}>
        <h2 className="mb-4 text-lg font-bold">Add Department</h2>
        
        <div className="space-y-4">
          <label className="block text-sm font-bold">
            Faculty
            <select className="field mt-1" value={facultyId} onChange={(e) => setFacultyId(Number(e.target.value))}>
              {faculties.map((f) => (
                <option key={f.id} value={f.id}>{f.name}</option>
              ))}
            </select>
          </label>

          <label className="block text-sm font-bold">
            Department Name
            <input
              className="field mt-1"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Computer Science"
              required
            />
          </label>

          <label className="block text-sm font-bold">
            Department Code
            <input
              className="field mt-1"
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              placeholder="e.g. CSC"
              maxLength={10}
              required
            />
          </label>

          <label className="block text-sm font-bold">
            Department Logo <span className="font-normal text-ink-soft">(optional, max 1 MB)</span>
            <input
              type="file"
              accept="image/*"
              className="field mt-1"
              onChange={(e) => {
                const f = e.target.files?.[0];
                if (f) {
                  if (f.size > 1024 * 1024) {
                    setErr('Logo must be under 1 MB');
                    setLogo(null);
                    return;
                  }
                  setLogo(f);
                  setErr('');
                }
              }}
            />
            {logo && (
              <p className="mt-1 text-xs text-green-600">✓ {logo.name} selected</p>
            )}
          </label>

          {err && <p role="alert" className="text-sm text-red-700">{err}</p>}
        </div>

        <div className="mt-6 flex justify-end gap-2">
          <button className="btn btn-ghost" onClick={onClose}>Cancel</button>
          <button
            className="btn btn-primary"
            onClick={() => create.mutate()}
            disabled={create.isPending || !name || !code}
          >
            {create.isPending ? 'Creating...' : 'Create Department'}
          </button>
        </div>
      </div>
    </div>
  );
}
