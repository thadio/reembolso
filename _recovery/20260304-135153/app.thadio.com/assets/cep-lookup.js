(() => {
  const normalizeCep = (value) => String(value || '').replace(/\D/g, '').slice(0, 8);

  const resolveField = (field) => {
    if (!field) return null;
    if (typeof field === 'string') {
      return document.querySelector(field);
    }
    return field;
  };

  const fetchCep = async (cep) => {
    const response = await fetch(`cep-lookup.php?cep=${encodeURIComponent(cep)}`, {
      headers: {
        Accept: 'application/json',
      },
    });

    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }

    if (!response.ok || !payload || payload.ok !== true) {
      const message = payload && payload.message ? payload.message : 'CEP não encontrado.';
      throw new Error(message);
    }

    return payload.data || {};
  };

  window.setupCepLookup = (config) => {
    const zipInput = resolveField(config.zip);
    if (!zipInput) return;

    const streetInput = resolveField(config.street);
    const address2Input = resolveField(config.address2);
    const neighborhoodInput = resolveField(config.neighborhood);
    const cityInput = resolveField(config.city);
    const stateInput = resolveField(config.state);
    const countryInput = resolveField(config.country);
    const countryDefault = config.countryDefault || 'BR';

    let lastLookup = '';
    let requestToken = 0;

    const applyData = (data) => {
      if (streetInput && data.logradouro) {
        streetInput.value = data.logradouro;
      }
      if (cityInput && data.city) {
        cityInput.value = data.city;
      }
      if (stateInput && data.state) {
        stateInput.value = String(data.state).toUpperCase();
      }
      if (countryInput) {
        const current = String(countryInput.value || '').trim();
        if (!current) {
          countryInput.value = countryDefault;
        }
      }
      if (neighborhoodInput) {
        const current = String(neighborhoodInput.value || '').trim();
        if (!current && data.bairro) {
          neighborhoodInput.value = data.bairro;
        }
      }
      if (address2Input) {
        const current = String(address2Input.value || '').trim();
        if (!current) {
          if (neighborhoodInput) {
            if (data.complemento) {
              address2Input.value = data.complemento;
            }
          } else {
            const parts = [];
            if (data.bairro) {
              parts.push(data.bairro);
            }
            if (data.complemento) {
              parts.push(data.complemento);
            }
            const combined = parts.join(' - ');
            if (combined) {
              address2Input.value = combined;
            }
          }
        }
      }
    };

    const runLookup = async () => {
      const cep = normalizeCep(zipInput.value);
      if (cep.length !== 8) return;
      if (cep === lastLookup) return;

      lastLookup = cep;
      const token = ++requestToken;
      zipInput.dataset.cepLookup = 'loading';

      try {
        const data = await fetchCep(cep);
        if (token !== requestToken) return;
        applyData(data);
        zipInput.dataset.cepLookup = 'done';
      } catch (error) {
        if (token !== requestToken) return;
        zipInput.dataset.cepLookup = 'error';
      }
    };

    const maybeLookup = () => {
      const cep = normalizeCep(zipInput.value);
      if (cep.length === 8) {
        runLookup();
      }
    };

    zipInput.addEventListener('blur', runLookup);
    zipInput.addEventListener('change', runLookup);
    zipInput.addEventListener('input', maybeLookup);
  };
})();
