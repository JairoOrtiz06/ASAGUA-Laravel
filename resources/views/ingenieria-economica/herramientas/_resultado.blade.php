<section class="section panel-link" aria-labelledby="resultado-calculo">
    <h3 id="resultado-calculo">Resultado</h3>
    @isset($resultado['valor'])
        <p><strong>{{ $resultado['valor'] }}</strong></p>
    @endisset
    <p>{{ $resultado['interpretacion'] }}</p>
    <p><strong>Fórmula</strong> <code>{{ $resultado['formula'] }}</code></p>
    <ol>
        @foreach ($resultado['pasos'] as $paso)
            <li>{{ $paso }}</li>
        @endforeach
    </ol>
    @isset($resultado['tabla'])
        <div class="table-wrap">
            <table aria-label="Factores financieros calculados">
                <thead>
                    <tr><th scope="col">Factor</th><th scope="col">Fórmula</th><th scope="col">Sustitución</th><th scope="col">Valor</th></tr>
                </thead>
                <tbody>
                    @foreach ($resultado['tabla'] as $codigo => $factor)
                        <tr>
                            <th scope="row">{{ $codigo }} · {{ $factor['nombre'] }}</th>
                            <td><code>{{ $factor['formula'] }}</code></td>
                            <td><code>{{ $factor['sustitucion'] }}</code></td>
                            <td>{{ $factor['valor'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endisset
    <p class="muted">{{ $resultado['simbolos'] }}</p>
</section>
