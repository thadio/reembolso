(() => {
  window.setTimeout(() => {
    document.querySelectorAll('.toast').forEach((toast) => {
      toast.style.transition = 'opacity 0.4s ease';
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 450);
    });
  }, 4500);

  const updateDropzoneText = (zone, input) => {
    const textNode = zone.querySelector('.dropzone-text');
    if (!textNode) {
      return;
    }

    const fallback = zone.getAttribute('data-default-text') || 'Arraste e solte arquivos aqui ou clique para selecionar.';
    const count = input.files ? input.files.length : 0;

    if (count <= 0) {
      textNode.textContent = fallback;
      return;
    }

    textNode.textContent = `${count} arquivo(s) selecionado(s).`;
  };

  document.querySelectorAll('.dropzone').forEach((zone) => {
    const inputId = zone.getAttribute('data-input-id');
    if (!inputId) {
      return;
    }

    const input = document.getElementById(inputId);
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const textNode = zone.querySelector('.dropzone-text');
    if (textNode) {
      zone.setAttribute('data-default-text', textNode.textContent || '');
    }

    zone.addEventListener('click', (event) => {
      if (event.target === input) {
        return;
      }

      input.click();
    });

    zone.addEventListener('dragover', (event) => {
      event.preventDefault();
      zone.classList.add('is-dragover');
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('is-dragover');
    });

    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      zone.classList.remove('is-dragover');

      if (!event.dataTransfer || event.dataTransfer.files.length === 0) {
        return;
      }

      try {
        if (typeof DataTransfer === 'function') {
          const transfer = new DataTransfer();
          Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
          input.files = transfer.files;
        } else {
          input.files = event.dataTransfer.files;
        }
      } catch (error) {
        return;
      }

      updateDropzoneText(zone, input);
    });

    input.addEventListener('change', () => updateDropzoneText(zone, input));
  });

  const initializePipelineBpmnEditor = async () => {
    const payload = window.__PIPELINE_BPMN_EDITOR__;
    const container = document.getElementById('pipeline-bpmn-editor');
    if (!payload || !(container instanceof HTMLElement)) {
      return;
    }

    const fitButton = document.getElementById('pipeline-bpmn-fit');
    const saveButton = document.getElementById('pipeline-bpmn-save');
    const statusNode = document.getElementById('pipeline-bpmn-status');
    const saveForm = document.getElementById('pipeline-bpmn-save-form');
    const xmlField = document.getElementById('pipeline-bpmn-xml');

    if (!(saveForm instanceof HTMLFormElement) || !(xmlField instanceof HTMLTextAreaElement)) {
      return;
    }

    const setStatus = (message, isError = false) => {
      if (!(statusNode instanceof HTMLElement)) {
        return;
      }

      statusNode.textContent = message;
      statusNode.classList.toggle('is-error', isError);
    };

    if (typeof window.BpmnJS !== 'function') {
      setStatus('Falha ao carregar a biblioteca BPMN.', true);
      return;
    }

    const toInt = (value, fallback = 0) => {
      const parsed = Number.parseInt(String(value ?? ''), 10);
      return Number.isFinite(parsed) ? parsed : fallback;
    };

    const cleanText = (value) => String(value ?? '').trim();

    const toNodeType = (nodeKind) => {
      if (nodeKind === 'gateway') {
        return 'bpmn:ExclusiveGateway';
      }

      if (nodeKind === 'final') {
        return 'bpmn:EndEvent';
      }

      return 'bpmn:Task';
    };

    const steps = Array.isArray(payload.steps)
      ? payload.steps
        .map((step) => ({
          status_id: toInt(step.status_id, 0),
          status_code: cleanText(step.status_code),
          status_label: cleanText(step.status_label),
          node_kind: cleanText(step.node_kind) || 'activity',
          sort_order: toInt(step.sort_order, 10),
          is_initial: toInt(step.is_initial, 0),
          is_active: toInt(step.is_active, 0),
        }))
        .filter((step) => step.status_id > 0 && step.is_active === 1)
        .sort((a, b) => a.sort_order - b.sort_order || a.status_id - b.status_id)
      : [];

    const transitions = Array.isArray(payload.transitions)
      ? payload.transitions
        .map((transition) => ({
          id: toInt(transition.id, 0),
          from_status_id: toInt(transition.from_status_id, 0),
          to_status_id: toInt(transition.to_status_id, 0),
          transition_label: cleanText(transition.transition_label),
          action_label: cleanText(transition.action_label),
          sort_order: toInt(transition.sort_order, 10),
          is_active: toInt(transition.is_active, 0),
        }))
        .filter((transition) => transition.id > 0 && transition.is_active === 1)
        .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
      : [];

    const modeler = new window.BpmnJS({
      container,
      keyboard: {
        bind: document,
      },
    });

    const fitViewport = () => {
      const canvas = modeler.get('canvas');
      canvas.zoom('fit-viewport', 'auto');
    };

    const createDiagramFromFlowData = async () => {
      await modeler.createDiagram();

      const modeling = modeler.get('modeling');
      const elementRegistry = modeler.get('elementRegistry');
      const canvas = modeler.get('canvas');
      const root = canvas.getRootElement();
      const startEvent = elementRegistry.filter((element) => element.type === 'bpmn:StartEvent')[0] || null;

      if (startEvent) {
        modeling.updateProperties(startEvent, { name: 'Inicio' });
      }

      if (steps.length === 0) {
        setStatus('Sem etapas ativas para desenhar. Cadastre etapas para iniciar o diagrama.', true);
        return;
      }

      const nodeMap = new Map();
      const columns = 4;
      const horizontalGap = 220;
      const verticalGap = 170;

      steps.forEach((step, index) => {
        const x = 220 + ((index % columns) * horizontalGap);
        const y = 190 + (Math.floor(index / columns) * verticalGap);
        const shape = modeling.createShape({ type: toNodeType(step.node_kind) }, { x, y }, root);
        const shapeId = `FlowNode_${step.status_id}`;
        const shapeLabel = step.status_label !== '' ? step.status_label : (step.status_code || shapeId);
        modeling.updateProperties(shape, { id: shapeId, name: shapeLabel });
        nodeMap.set(step.status_id, shape);
      });

      const initialStep = steps.find((step) => step.is_initial === 1) || steps[0];
      if (startEvent && initialStep) {
        const initialShape = nodeMap.get(initialStep.status_id);
        if (initialShape) {
          try {
            modeling.connect(startEvent, initialShape, { type: 'bpmn:SequenceFlow' });
          } catch (error) {
          }
        }
      }

      let hasValidTransition = false;
      transitions.forEach((transition) => {
        const source = nodeMap.get(transition.from_status_id);
        const target = nodeMap.get(transition.to_status_id);
        if (!source || !target || source === target) {
          return;
        }

        try {
          const connection = modeling.connect(source, target, { type: 'bpmn:SequenceFlow' });
          if (connection) {
            hasValidTransition = true;
            const connectionLabel = transition.transition_label || transition.action_label;
            if (connectionLabel !== '') {
              modeling.updateProperties(connection, { name: connectionLabel });
            }
          }
        } catch (error) {
        }
      });

      if (!hasValidTransition && steps.length > 1) {
        for (let index = 0; index < steps.length - 1; index += 1) {
          const source = nodeMap.get(steps[index].status_id);
          const target = nodeMap.get(steps[index + 1].status_id);
          if (!source || !target) {
            continue;
          }

          try {
            modeling.connect(source, target, { type: 'bpmn:SequenceFlow' });
          } catch (error) {
          }
        }
      }
    };

    const saveDiagram = async () => {
      if (!(saveButton instanceof HTMLButtonElement)) {
        return;
      }

      saveButton.disabled = true;
      if (fitButton instanceof HTMLButtonElement) {
        fitButton.disabled = true;
      }

      try {
        setStatus('Gerando XML BPMN...');
        const { xml } = await modeler.saveXML({ format: true });
        if (cleanText(xml) === '') {
          throw new Error('empty_xml');
        }

        xmlField.value = xml;
        setStatus('Salvando diagrama BPMN...');
        saveForm.submit();
      } catch (error) {
        setStatus('Nao foi possivel salvar o diagrama BPMN.', true);
        saveButton.disabled = false;
        if (fitButton instanceof HTMLButtonElement) {
          fitButton.disabled = false;
        }
      }
    };

    if (fitButton instanceof HTMLButtonElement) {
      fitButton.addEventListener('click', () => {
        fitViewport();
      });
    }

    if (saveButton instanceof HTMLButtonElement) {
      saveButton.addEventListener('click', () => {
        void saveDiagram();
      });
    }

    try {
      const storedXml = cleanText(payload.diagram_xml);
      if (storedXml !== '') {
        await modeler.importXML(storedXml);
        setStatus('Diagrama carregado. Arraste elementos para editar e clique em salvar.');
      } else {
        await createDiagramFromFlowData();
        setStatus('Diagrama inicial gerado pelas etapas/transicoes atuais.');
      }
    } catch (error) {
      setStatus('Falha ao importar o XML salvo. Gerando diagrama inicial...', true);

      try {
        await createDiagramFromFlowData();
        setStatus('Diagrama inicial carregado. Ajuste e salve para persistir.');
      } catch (innerError) {
        setStatus('Nao foi possivel inicializar o editor BPMN.', true);
      }
    }

    fitViewport();
  };

  void initializePipelineBpmnEditor();
})();
