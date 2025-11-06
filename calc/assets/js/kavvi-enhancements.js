// assets/js/kavvi-enhancements.js
(function () {
  const $ = (sel, ctx=document) => ctx.querySelector(sel);

  // ---------- CEP -> ViaCEP ----------
  const cepInput = $('#cliente_cep');
  if (cepInput) {
    cepInput.addEventListener('blur', async () => {
      const raw = (cepInput.value || '').replace(/\D/g, '');
      if (raw.length !== 8) return;
      try {
        const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        if (!res.ok) throw new Error('CEP não encontrado');
        const data = await res.json();
        if (data.erro) throw new Error('CEP inválido');
        $('#cliente_endereco') && ($('#cliente_endereco').value = data.logradouro || '');
        $('#cliente_bairro') && ($('#cliente_bairro').value = data.bairro || '');
        $('#cliente_cidade') && ($('#cliente_cidade').value = data.localidade || '');
        $('#cliente_uf') && ($('#cliente_uf').value = data.uf || '');
      } catch (e) {
        console.warn('ViaCEP falhou:', e.message);
      }
    });
  }

  // ---------- CNPJ -> BrasilAPI ----------
  const cnpjInput = $('#cliente_cnpj');
  if (cnpjInput) {
    cnpjInput.addEventListener('blur', async () => {
      const raw = (cnpjInput.value || '').replace(/\D/g, '');
      if (raw.length !== 14) return;
      try {
        const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${raw}`);
        if (!res.ok) throw new Error('CNPJ não encontrado');
        const data = await res.json();
        $('#cliente_empresa') && ($('#cliente_empresa').value = data.razao_social || data.nome_fantasia || '');
        $('#cliente_endereco') && ($('#cliente_endereco').value = data.logradouro || '');
        $('#cliente_numero') && ($('#cliente_numero').value = data.numero || '');
        $('#cliente_bairro') && ($('#cliente_bairro').value = data.bairro || '');
        $('#cliente_cidade') && ($('#cliente_cidade').value = data.municipio || '');
        $('#cliente_uf') && ($('#cliente_uf').value = data.uf || '');
        $('#cliente_cep') && ($('#cliente_cep').value = (data.cep || '').replace(/\D/g, ''));
      } catch (e) {
        console.warn('BrasilAPI falhou:', e.message);
      }
    });
  }
})();
