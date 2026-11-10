import { useState, useRef, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/shared/api';

interface UserLite {
  id: number;
  name: string;
  title?: string;
  unit_id: number | null;
}

interface StaffAutocompleteProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  label: string;
  required?: boolean;
}

export function StaffAutocomplete({ value, onChange, placeholder, label, required }: StaffAutocompleteProps) {
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [inputValue, setInputValue] = useState(value);
  const wrapperRef = useRef<HTMLDivElement>(null);

  // Search for users when typing
  const { data: suggestions = [] } = useQuery({
    queryKey: ['user-search', inputValue],
    enabled: inputValue.length >= 2 && showSuggestions,
    queryFn: async () => (await api.get<UserLite[]>('/users/search', { params: { q: inputValue } })).data,
  });

  // Sync external value changes
  useEffect(() => {
    setInputValue(value);
  }, [value]);

  // Close suggestions when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
        setShowSuggestions(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleInputChange = (val: string) => {
    setInputValue(val);
    onChange(val);
    setShowSuggestions(val.length >= 2);
  };

  const selectSuggestion = (user: UserLite) => {
    const formatted = user.title ? `${user.name}, ${user.title}` : user.name;
    setInputValue(formatted);
    onChange(formatted);
    setShowSuggestions(false);
  };

  return (
    <div ref={wrapperRef} className="relative">
      <label className="block text-sm font-bold">
        {label}
        <input
          className="field mt-1"
          value={inputValue}
          onChange={(e) => handleInputChange(e.target.value)}
          onFocus={() => inputValue.length >= 2 && setShowSuggestions(true)}
          placeholder={placeholder}
          required={required}
          maxLength={255}
          autoComplete="off"
        />
      </label>

      {showSuggestions && suggestions.length > 0 && (
        <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-surface-line bg-white shadow-lg">
          {suggestions.map((user) => (
            <li key={user.id}>
              <button
                type="button"
                className="w-full px-4 py-2 text-left text-sm hover:bg-brand-50"
                onClick={() => selectSuggestion(user)}
              >
                <span className="font-bold">{user.name}</span>
                {user.title && <span className="text-ink-soft"> · {user.title}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}

      {showSuggestions && inputValue.length >= 2 && suggestions.length === 0 && (
        <div className="absolute z-10 mt-1 w-full rounded-lg border border-surface-line bg-white px-4 py-2 text-sm text-ink-soft shadow-lg">
          No staff members found. You can still type manually.
        </div>
      )}
    </div>
  );
}
