(() => {
    const dni = document.getElementById('dni');
    const status = document.getElementById('docente-persona-status');
    if (!dni || !status) return;
    const form = dni.form;
    const fields = ['nombre', 'apellido', 'email'].map(id => document.getElementById(id));
    document.getElementById('docente-vinculacion-fallback')?.remove();
    const save = form.querySelector('button[type="submit"]');
    const linkValue = document.createElement('input');
    linkValue.type = 'hidden'; linkValue.name = 'vincular'; form.append(linkValue);
    let timer, controller, version = 0, filled = false;
    const lookup = async () => {
        const request = ++version;
        controller?.abort(); controller = new AbortController();
        linkValue.value = '';
        if (filled) fields.forEach(field => { field.value = ''; field.readOnly = false; });
        filled = false; status.replaceChildren(); save.disabled = true;
        const value = dni.value.trim();
        if (!/^[0-9]{7,9}$/.test(value)) { status.textContent = 'Ingresá un DNI de 7 a 9 dígitos, sin puntos.'; return; }
        status.textContent = 'Buscando persona…';
        try {
            const body = new URLSearchParams({dni: value, _csrf: form.elements._csrf.value});
            const response = await fetch('/admin/docentes/buscar-persona.php', {method: 'POST', body, signal: controller.signal, cache: 'no-store'});
            if (!response.ok || response.redirected) throw new Error();
            const {persona} = await response.json();
            if (request !== version) return;
            if (!persona) {
                status.textContent = 'DNI no registrado. Completá los datos para crear la persona y su perfil docente.';
                save.disabled = false; return;
            }
            fields.forEach(field => { field.value = persona[field.id] || ''; field.readOnly = true; }); filled = true;
            if (persona.docente_id) {
                status.textContent = 'Esta persona ya tiene un perfil docente. ';
                const link = document.createElement('a'); link.className = 'activity-action-secondary';
                link.href = '/admin/docentes/editar.php?id=' + Number(persona.docente_id);
                link.textContent = 'Editar docente'; status.append(link); return;
            }
            if (persona.estado !== 'activo') { status.textContent = 'Esta persona está inactiva. Revisá su ficha antes de vincularla.'; return; }
            linkValue.value = persona.id;
            status.textContent = 'Persona encontrada. Se conservarán sus datos; completá el título, la biografía y la foto del docente.';
            save.disabled = false;
        } catch (error) {
            if (request !== version || error.name === 'AbortError') return;
            status.textContent = 'No se pudo consultar el DNI. Revisá tu sesión y volvé a buscar.';
        }
    };
    dni.addEventListener('input', () => {
        ++version; controller?.abort(); clearTimeout(timer); save.disabled = true; linkValue.value = '';
        if (filled) fields.forEach(field => { field.value = ''; field.readOnly = false; });
        filled = false; status.textContent = 'Esperando DNI…'; timer = setTimeout(lookup, 400);
    });
    document.getElementById('docente-persona-buscar').addEventListener('click', () => { clearTimeout(timer); lookup(); });
    form.addEventListener('submit', event => { if (save.disabled) event.preventDefault(); });
    if (dni.value.trim()) lookup(); else save.disabled = true;
})();
