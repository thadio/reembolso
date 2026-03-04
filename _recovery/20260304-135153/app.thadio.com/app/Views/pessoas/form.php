<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $history */
/** @var bool $isTrashed */
/** @var array $roleOptions */
/** @var callable $esc */
?>
<?php
  $selectedRoles = [];
  if (!empty($formData['roles']) && is_array($formData['roles'])) {
      foreach ($formData['roles'] as $role) {
          $selectedRoles[(string) $role] = true;
      }
  }
  $roleOptions = $roleOptions ?? [];
  $requestedRole = isset($_GET['role']) ? trim((string) $_GET['role']) : '';
  if ($requestedRole === '' && isset($_POST['role'])) {
      $requestedRole = trim((string) $_POST['role']);
  }
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Pessoas</h1>
      <div class="subtitle">CRUD centralizado no app + papeis locais.</div>
    </div>
    <span class="pill">
      <?php
        $pill = $editing ? 'Editando ID #' . $esc((string) $formData['id']) : 'Nova pessoa';
        if (!empty($isTrashed)) {
            $pill .= ' - Lixeira';
        }
        echo $pill;
      ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="pessoa-cadastro.php<?php echo $requestedRole !== '' && !$editing ? '?role=' . $esc($requestedRole) : ''; ?>">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <?php if ($requestedRole !== ''): ?>
      <input type="hidden" name="role" value="<?php echo $esc($requestedRole); ?>">
    <?php endif; ?>

    <div class="grid">
      <div class="field">
        <label for="fullName">Nome completo *</label>
        <input type="text" id="fullName" name="fullName" required maxlength="200" value="<?php echo $esc($formData['fullName']); ?>">
      </div>
      <div class="field">
        <label for="email">E-mail *</label>
        <input type="email" id="email" name="email" required maxlength="180" value="<?php echo $esc($formData['email']); ?>">
      </div>
      <div class="field">
        <label for="email2">E-mail secundário</label>
        <input type="email" id="email2" name="email2" maxlength="180" value="<?php echo $esc($formData['email2'] ?? ''); ?>">
      </div>
      <div class="field">
        <label for="phone">Telefone</label>
        <input type="text" id="phone" name="phone" maxlength="50" value="<?php echo $esc($formData['phone']); ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field">
        <label for="cpfCnpj">CPF/CNPJ</label>
        <input type="text" id="cpfCnpj" name="cpfCnpj" maxlength="40" value="<?php echo $esc($formData['cpfCnpj']); ?>">
      </div>
      <div class="field">
        <label for="pixKey">Chave PIX</label>
        <input type="text" id="pixKey" name="pixKey" maxlength="180" value="<?php echo $esc($formData['pixKey']); ?>">
      </div>
      <div class="field">
        <label for="instagram">Instagram</label>
        <input type="text" id="instagram" name="instagram" maxlength="120" value="<?php echo $esc($formData['instagram']); ?>">
      </div>
      <div class="field">
        <label for="zip">CEP</label>
        <input type="text" id="zip" name="zip" maxlength="30" value="<?php echo $esc($formData['zip']); ?>">
      </div>
      <div class="field">
        <label for="street">Rua</label>
        <input type="text" id="street" name="street" maxlength="200" value="<?php echo $esc($formData['street']); ?>">
      </div>
      <div class="field">
        <label for="number">Número</label>
        <input type="text" id="number" name="number" maxlength="40" value="<?php echo $esc($formData['number']); ?>">
      </div>
      <div class="field">
        <label for="street2">Complemento</label>
        <input type="text" id="street2" name="street2" placeholder="nº, cs, lt, apt, bl" autocomplete="address-line2" maxlength="200" value="<?php echo $esc($formData['street2']); ?>">
      </div>
      <div class="field">
        <label for="neighborhood">Bairro</label>
        <input type="text" id="neighborhood" name="neighborhood" maxlength="120" value="<?php echo $esc($formData['neighborhood']); ?>">
      </div>
      <div class="field">
        <label for="city">Cidade</label>
        <input type="text" id="city" name="city" maxlength="120" value="<?php echo $esc($formData['city']); ?>">
      </div>
      <div class="field">
        <label for="state">Estado</label>
        <input type="text" id="state" name="state" maxlength="80" value="<?php echo $esc($formData['state']); ?>">
      </div>
      <div class="field">
        <label for="country">País</label>
        <input type="text" id="country" name="country" maxlength="100" value="<?php echo $esc($formData['country']); ?>">
      </div>
    </div>

    <div style="margin-top:24px;">
      <h2>Papéis</h2>
      <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <?php foreach ($roleOptions as $role => $label): ?>
          <?php
            $checked = isset($selectedRoles[$role]);
          ?>
          <label class="field" style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="roles[]" value="<?php echo $esc($role); ?>" <?php echo $checked ? 'checked' : ''; ?> data-role="<?php echo $esc($role); ?>">
            <span><?php echo $esc($label); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php
      $availableRoles = $formData['roles'] ?? [];
      if (!is_array($availableRoles)) {
          $availableRoles = $availableRoles === '' ? [] : [(string) $availableRoles];
      }
      $normalizedRoles = [];
      foreach ($availableRoles as $role) {
          $cleanRole = trim((string) $role);
          if ($cleanRole !== '') {
              $normalizedRoles[] = $cleanRole;
          }
      }
      $vendorRoleCandidates = ['fornecedor', 'consignante'];
      $hasVendorRole = (bool) array_intersect($vendorRoleCandidates, $normalizedRoles);
      $vendorRoleAttr = implode(' ', $vendorRoleCandidates);
      $initialRoleAttr = implode(' ', $normalizedRoles);
    ?>
    <div
      id="vendorDataSection"
      class="vendor-section"
      data-vendor-roles="<?php echo $esc($vendorRoleAttr); ?>"
      data-initial-roles="<?php echo $esc($initialRoleAttr); ?>"
      <?php echo $hasVendorRole ? '' : 'style="display:none;"'; ?>
    >
      <div style="margin-top:24px;">
        <h2>Dados de Fornecedor</h2>
        <div class="subtitle">Preencha apenas se esta pessoa também for fornecedora/consignante.</div>
        <div class="grid">
          <div class="field">
            <label for="vendorCommissionRate">% Comissão (consignação)</label>
            <input type="text" inputmode="decimal" data-number-br step="0.01" min="0" max="100" id="vendorCommissionRate" name="vendorCommissionRate" value="<?php echo $esc((string) ($formData['vendorCommissionRate'] ?? '')); ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar pessoa</button>
    </div>
  </form>

  <?php if ($editing): ?>
    <div style="margin-top:24px;">
      <h2>Histórico</h2>
      <?php if (empty($history)): ?>
        <div class="muted">Nenhum histórico registrado.</div>
      <?php else: ?>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>Data</th>
                <th>Ação</th>
                <th>Usuário</th>
                <th>Alterações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $row): ?>
                <tr>
                  <td><?php echo $esc((string) ($row['created_at'] ?? '')); ?></td>
                  <td><?php echo $esc((string) ($row['action_label'] ?? $row['action'] ?? '')); ?></td>
                  <td><?php echo $esc((string) ($row['user_email'] ?? '-')); ?></td>
                  <td>
                    <?php if (!empty($row['changes'])): ?>
                      <div class="muted">
                        <?php foreach ($row['changes'] as $change): ?>
                          <div>
                            <strong><?php echo $esc((string) ($change['label'] ?? '')); ?></strong>:
                            <?php echo $esc((string) (($change['before'] ?? '') !== '' ? $change['before'] : '-')); ?>
                            -> <?php echo $esc((string) (($change['after'] ?? '') !== '' ? $change['after'] : '-')); ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    if (window.setupCepLookup) {
      window.setupCepLookup({
        zip: '#zip',
        street: '#street',
        address2: '#street2',
        neighborhood: '#neighborhood',
        city: '#city',
        state: '#state',
        country: '#country',
        countryDefault: 'BR',
      });
    }

    const vendorSection = document.getElementById('vendorDataSection');
    if (!vendorSection) {
      return;
    }

    const vendorRoles = (vendorSection.dataset.vendorRoles ?? '').split(' ').filter((role) => role !== '');
    const initialRoles = (vendorSection.dataset.initialRoles ?? '').split(' ').filter((role) => role !== '');
    const trackedRoles = new Set(initialRoles);
    const roleInputs = Array.from(document.querySelectorAll('input[name="roles[]"][type="checkbox"][data-role]'));

    const updateTrackedRole = (role, checked) => {
      if (!role) {
        return;
      }
      if (checked) {
        trackedRoles.add(role);
      } else {
        trackedRoles.delete(role);
      }
    };

    const updateVendorVisibility = () => {
      const shouldShow = vendorRoles.some((role) => trackedRoles.has(role));
      vendorSection.style.display = shouldShow ? '' : 'none';
    };

    roleInputs.forEach((checkbox) => {
      const role = checkbox.dataset.role ?? '';
      updateTrackedRole(role, checkbox.checked);
      checkbox.addEventListener('change', () => {
        updateTrackedRole(role, checkbox.checked);
        updateVendorVisibility();
      });
    });

    updateVendorVisibility();
  });
</script>
