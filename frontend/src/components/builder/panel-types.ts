/**
 * The interface every block type's Content-tab panel implements — a
 * controlled form over its settings object, nothing else. Panels never
 * call the API directly; BuilderPanel owns debounced-autosave and the
 * undo/redo stack around whatever `onChange` produces.
 */
export interface ContentPanelProps<T> {
  value: T;
  onChange: (value: T) => void;
  storeId: number;
}
