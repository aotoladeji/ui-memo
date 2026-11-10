import { useState } from 'react';
import { Table } from 'lucide-react';

interface TableBuilderProps {
  onInsert: (tableText: string) => void;
}

export function TableBuilder({ onInsert }: TableBuilderProps) {
  const [showDialog, setShowDialog] = useState(false);
  const [rows, setRows] = useState(3);
  const [cols, setCols] = useState(3);
  const [cells, setCells] = useState<string[][]>([]);
  const [hasHeader, setHasHeader] = useState(true);

  const openDialog = () => {
    // Initialize empty table
    const initialCells = Array(rows).fill(null).map(() => Array(cols).fill(''));
    setCells(initialCells);
    setShowDialog(true);
  };

  const updateDimensions = (newRows: number, newCols: number) => {
    const updated = Array(newRows).fill(null).map((_, r) =>
      Array(newCols).fill(null).map((_, c) => cells[r]?.[c] ?? '')
    );
    setCells(updated);
    setRows(newRows);
    setCols(newCols);
  };

  const updateCell = (row: number, col: number, value: string) => {
    const updated = cells.map((r, i) => 
      i === row ? r.map((c, j) => j === col ? value : c) : r
    );
    setCells(updated);
  };

  const insertTable = () => {
    if (cells.length === 0) return;

    // Create a JSON representation that we'll parse in the preview
    const tableData = {
      type: 'table',
      hasHeader,
      rows: cells
    };
    
    // Insert as a special marker that we can detect and render
    const tableMarker = `\n[TABLE:${JSON.stringify(tableData)}]\n`;
    
    onInsert(tableMarker);
    setShowDialog(false);
  };

  return (
    <>
      <button
        type="button"
        className="btn btn-ghost flex items-center gap-2 text-sm"
        onClick={openDialog}
      >
        <Table size={16} /> Insert Table
      </button>

      {showDialog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" onClick={() => setShowDialog(false)}>
          <div className="card max-h-[90vh] w-full max-w-4xl overflow-auto bg-white p-6" onClick={(e) => e.stopPropagation()}>
            <h3 className="mb-4 text-lg font-bold">Insert Table</h3>

            <div className="mb-4 flex flex-wrap items-center gap-4">
              <label className="flex items-center gap-2 text-sm">
                <span className="font-bold">Rows:</span>
                <input
                  type="number"
                  min="1"
                  max="20"
                  value={rows}
                  onChange={(e) => updateDimensions(Math.max(1, Math.min(20, parseInt(e.target.value) || 1)), cols)}
                  className="field w-20"
                />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <span className="font-bold">Columns:</span>
                <input
                  type="number"
                  min="1"
                  max="10"
                  value={cols}
                  onChange={(e) => updateDimensions(rows, Math.max(1, Math.min(10, parseInt(e.target.value) || 1)))}
                  className="field w-20"
                />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={hasHeader}
                  onChange={(e) => setHasHeader(e.target.checked)}
                />
                <span>First row is header</span>
              </label>
            </div>

            <div className="mb-4 overflow-auto">
              <table className="w-full border-collapse">
                <tbody>
                  {cells.map((row, rowIndex) => (
                    <tr key={rowIndex}>
                      {row.map((cell, colIndex) => (
                        <td key={colIndex} className="border border-surface-line p-1">
                          <input
                            type="text"
                            value={cell}
                            onChange={(e) => updateCell(rowIndex, colIndex, e.target.value)}
                            className={`field w-full ${rowIndex === 0 && hasHeader ? 'font-bold' : ''}`}
                            placeholder={rowIndex === 0 && hasHeader ? `Header ${colIndex + 1}` : `Cell ${rowIndex + 1},${colIndex + 1}`}
                          />
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="mb-4 rounded-lg bg-surface-soft p-3">
              <p className="mb-1 text-xs font-bold text-ink-soft">Preview:</p>
              {cells.length > 0 && (
                <div className="overflow-auto">
                  <table className="w-full border-collapse border border-gray-300 bg-white text-xs">
                    <tbody>
                      {cells.map((row, rowIndex) => (
                        <tr key={rowIndex}>
                          {row.map((cell, colIndex) => {
                            const Tag = (rowIndex === 0 && hasHeader) ? 'th' : 'td';
                            return (
                              <Tag
                                key={colIndex}
                                className={`border border-gray-300 px-2 py-1 text-left ${rowIndex === 0 && hasHeader ? 'bg-gray-100 font-bold' : ''}`}
                              >
                                {cell || '\u00A0'}
                              </Tag>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="flex justify-end gap-2">
              <button className="btn btn-ghost" onClick={() => setShowDialog(false)}>Cancel</button>
              <button className="btn btn-primary" onClick={insertTable}>Insert Table</button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
