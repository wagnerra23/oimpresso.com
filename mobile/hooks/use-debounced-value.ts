import { useEffect, useState } from "react";

/**
 * Returns a value that lags behind `value` by `delay` ms.
 * Used to throttle search inputs (F1-04) so list filtering doesn't
 * re-render on every keystroke.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const handle = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(handle);
  }, [value, delay]);

  return debounced;
}
