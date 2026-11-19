# Autenticación y usuarios — decisiones de la Tarea 2

- La autenticación usa `App\Models\User` sobre `tblusuarios`; la tabla estándar `users` de Laravel se conserva porque pertenece a las migraciones iniciales del framework, pero no es la fuente de usuarios ASAGUA.
- El login consulta `usuario`, `password` y `activo=true`, igual que `verificar_credenciales` del proyecto Python.
- Los hashes BCrypt existentes se validan mediante el guard de Laravel. No se aplica el cast `hashed` al modelo para evitar re-hashear o alterar hashes heredados.
- Los únicos roles aceptados son `Admin`, `Contador` y `Auditor`.
- Admin accede al panel administrativo y a gestión de usuarios. Contador y Auditor solo acceden a sus paneles en esta tarea; los módulos contables se agregarán después.
- La gestión permite listar, buscar, registrar, editar, asignar rol y activar/desactivar. No se expone eliminación física en esta tarea para no realizar una operación destructiva sobre usuarios referenciables.
- El seeder estándar quedó sin creación automática de usuarios ficticios.
- La validación de contraseña exige presencia al registrar y permite dejarla vacía al editar para conservarla, como en la interfaz Python. No se agregó longitud mínima porque el sistema original no la define.
