# Troubleshooting Alpine.js Intersect Plugin

## Problema

Los elementos con `x-intersect` no se muestran cuando se hace scroll. Las secciones con clase `snn-lazy-section` permanecen ocultas.

## Sintaxis Correcta

### ❌ Sintaxis Incorrecta (puede causar problemas)

```html
<div 
    x-data="{ shown: false }"
    x-intersect.once.margin.20%="shown = true"
    :class="shown ? 'is-visible' : ''"
>
```

**Problema**: Los puntos (`.`) en los nombres de atributos HTML pueden causar problemas de parsing.

### ✅ Sintaxis Correcta (Recomendada)

**Opción 1: Usar sintaxis con guiones**

```html
<div 
    x-data="{ shown: false }"
    x-intersect-once="shown = true"
    x-intersect-margin="20%"
    :class="shown ? 'is-visible' : ''"
    class="snn-lazy-section"
>
```

**Opción 2: Usar sintaxis con corchetes (más compatible)**

```html
<div 
    x-data="{ shown: false }"
    x-intersect.once="shown = true"
    x-intersect.margin="20%"
    :class="shown ? 'is-visible' : ''"
    class="snn-lazy-section"
>
```

**Opción 3: Usar expresión completa en un solo atributo**

```html
<div 
    x-data="{ shown: false }"
    x-intersect="shown = true"
    x-intersect-once
    x-intersect-margin="20%"
    :class="shown ? 'is-visible' : ''"
    class="snn-lazy-section"
>
```

## Verificación del Plugin

### 1. Verificar que el plugin se carga

Abre la consola del navegador y verifica:

```javascript
// Debe retornar true
typeof Alpine !== 'undefined'

// Debe retornar true (si el plugin se auto-registra)
typeof intersect !== 'undefined'
```

### 2. Verificar elementos con x-intersect

```javascript
// Debe encontrar tus elementos
document.querySelectorAll('[x-intersect]')
document.querySelectorAll('.snn-lazy-section')
```

### 3. Verificar que Alpine procesa los elementos

```javascript
// Los elementos con x-data deben tener __x
document.querySelector('.snn-lazy-section').__x
```

## Soluciones

### Solución 1: Cambiar la sintaxis del atributo

En lugar de:
```html
x-intersect.once.margin.20%="shown = true"
```

Usa:
```html
x-intersect.once="shown = true"
x-intersect.margin="20%"
```

O mejor aún:
```html
x-intersect="shown = true"
x-intersect-once
x-intersect-margin="20%"
```

### Solución 2: Verificar el orden de carga

1. **Alpine Core** debe cargarse primero
2. **Alpine Intersect Plugin** debe cargarse después
3. Ambos deben estar disponibles antes de que Alpine se auto-inicialice

### Solución 3: Usar CSS para ocultar inicialmente

Asegúrate de que los elementos estén ocultos inicialmente con CSS:

```css
.snn-lazy-section {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.snn-lazy-section.is-visible {
    opacity: 1;
    transform: translateY(0);
}
```

### Solución 4: Verificar que Alpine no se inicializa antes del plugin

Si Alpine se auto-inicializa antes de que el plugin esté listo, puedes prevenir la auto-inicialización:

```html
<script>
// Prevenir auto-inicialización
window.Alpine = { start: false };
</script>
```

Luego, después de que ambos scripts se carguen:

```html
<script>
// Registrar plugin y luego inicializar
Alpine.plugin(intersect);
Alpine.start();
</script>
```

## Debugging

### Habilitar logs en consola

El código actual incluye logs de debugging. Abre la consola y busca:

- `[Alpine Intersect] Plugin registrado correctamente`
- `[Alpine Intersect] Encontrados X elementos con x-intersect`

### Verificar en Network tab

1. Abre DevTools > Network
2. Recarga la página
3. Verifica que `alpine.js` y `ialpine.min.js` se carguen correctamente
4. Verifica el orden de carga (alpine.js primero, luego ialpine.min.js)

## Ejemplo Completo Funcional

```html
<div 
    class="snn-lazy-section"
    x-data="{ shown: false }"
    x-intersect="shown = true"
    x-intersect-once
    x-intersect-margin="20%"
    :class="shown ? 'is-visible' : ''"
    style="opacity: 0; transition: opacity 0.3s;"
    :style="shown ? 'opacity: 1;' : 'opacity: 0;'"
>
    Contenido que se mostrará al hacer scroll
</div>
```

## Notas Importantes

1. **Los puntos en atributos HTML**: Los puntos (`.`) en nombres de atributos pueden causar problemas. Usa guiones (`-`) o separa los atributos.

2. **Auto-inicialización de Alpine**: Alpine 3.x se auto-inicializa cuando detecta `x-data`. Asegúrate de que el plugin Intersect esté registrado antes.

3. **Orden de carga**: El plugin Intersect debe cargarse después de Alpine Core pero antes de que Alpine se inicialice.

4. **Sintaxis de margin**: El margen puede especificarse como `20%` o `200px`. Asegúrate de usar comillas si hay espacios.

