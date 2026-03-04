(() => {
  const DECIMAL_THRESHOLD = 2;

  const normalizeSingleSeparator = (raw, separator, threshold) => {
    const count = (raw.match(new RegExp(`\\${separator}`, 'g')) || []).length;
    if (count === 0) return raw;
    const lastPos = raw.lastIndexOf(separator);
    const digitsAfter = lastPos >= 0 ? raw.length - lastPos - 1 : 0;
    if (count > 1) {
      if (digitsAfter <= threshold) {
        const before = raw.slice(0, lastPos).split(separator).join('');
        const after = raw.slice(lastPos + 1).split(separator).join('');
        return `${before}.${after}`;
      }
      return raw.split(separator).join('');
    }
    if (digitsAfter <= threshold) {
      return raw.replace(separator, '.');
    }
    return raw.replace(separator, '');
  };

  const parseNumber = (value) => {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    let raw = String(value).trim();
    if (!raw) return null;
    raw = raw.replace(/\s+/g, '');
    let sign = '';
    const firstChar = raw[0];
    if (firstChar === '-' || firstChar === '+') {
      sign = firstChar;
      raw = raw.slice(1);
    }
    raw = raw.replace(/[^0-9,\\.]/g, '');
    if (!raw) return null;

    const lastComma = raw.lastIndexOf(',');
    const lastDot = raw.lastIndexOf('.');
    if (lastComma !== -1 && lastDot !== -1) {
      const decimalChar = lastComma > lastDot ? ',' : '.';
      const thousandChar = decimalChar === ',' ? '.' : ',';
      raw = raw.split(thousandChar).join('');
      const decimalPos = raw.lastIndexOf(decimalChar);
      const before = decimalPos >= 0 ? raw.slice(0, decimalPos) : raw;
      const after = decimalPos >= 0 ? raw.slice(decimalPos + 1) : '';
      const cleanBefore = before.split(decimalChar).join('');
      const cleanAfter = after.split(decimalChar).join('');
      raw = `${cleanBefore}${decimalPos >= 0 ? '.' : ''}${cleanAfter}`;
    } else if (lastComma !== -1) {
      raw = normalizeSingleSeparator(raw, ',', DECIMAL_THRESHOLD);
    } else if (lastDot !== -1) {
      raw = normalizeSingleSeparator(raw, '.', DECIMAL_THRESHOLD);
    }

    if (!/[0-9]/.test(raw)) return null;
    const numeric = Number(`${sign}${raw}`);
    return Number.isFinite(numeric) ? numeric : null;
  };

  const formatNumber = (value, decimals = 2) => {
    if (!Number.isFinite(value)) return '';
    const safeDecimals = Number.isFinite(decimals) ? Math.max(0, Math.min(6, decimals)) : 2;
    return value.toLocaleString('pt-BR', {
      minimumFractionDigits: safeDecimals,
      maximumFractionDigits: safeDecimals,
    });
  };

  const decimalsFromStep = (input) => {
    const step = input.getAttribute('step');
    if (!step || step === 'any') return null;
    const normalized = String(step).replace(',', '.');
    const dotPos = normalized.indexOf('.');
    if (dotPos === -1) return 0;
    return Math.max(0, normalized.length - dotPos - 1);
  };

  const resolveDecimals = (input) => {
    if (input.dataset.decimals !== undefined && input.dataset.decimals !== '') {
      const parsed = parseInt(input.dataset.decimals, 10);
      if (Number.isFinite(parsed)) return parsed;
    }
    const fromStep = decimalsFromStep(input);
    if (fromStep !== null) return fromStep;
    return 2;
  };

  const formatInputValue = (input) => {
    const raw = input.value;
    if (!raw) return;
    const parsed = parseNumber(raw);
    if (parsed === null) return;
    const decimals = resolveDecimals(input);
    input.value = formatNumber(parsed, decimals);
  };

  const bindInputs = () => {
    document.querySelectorAll('[data-number-br]').forEach((input) => {
      formatInputValue(input);
      input.addEventListener('blur', () => formatInputValue(input));
    });
    document.addEventListener('focusout', (event) => {
      const target = event.target;
      if (target && target.matches && target.matches('[data-number-br]')) {
        formatInputValue(target);
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindInputs);
  } else {
    bindInputs();
  }

  window.RetratoNumber = {
    parse: parseNumber,
    format: formatNumber,
    formatInput: formatInputValue,
  };
})();
