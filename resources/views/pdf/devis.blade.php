<h1>DEVIS {{ $devis->reference }}</h1> 

<h3>Date</h3>
<p>{{ $devis->created_at }}</p>

<h3>Client</h3>
<p>{{ $devis->client_name }}</p>
<p>{{ $devis->site_address }} – {{ $devis->site_city }}</p>

<h3>Ouvrage</h3>
<p>Type : {{ ucfirst($devis->work_type) }}</p>
<p>Surface totale : {{ $devis->total_surface }} m²</p>

<h3>Montant estimatif</h3>
<p><strong>{{ number_format($devis->estimated_amount, 2) }} DH</strong></p>

<hr>

<h3>Annexe – Hypothèses & règles</h3>
<ul>
    <li>DTU : {{ $devis->assumptions['DTU'] }}</li>
    <li>Entraxe : {{ $devis->assumptions['entraxe'] }}</li>
    <li>Plaque : {{ $devis->assumptions['plaque'] }}</li>
</ul>

<p style="font-size:12px">
⚠ Estimation indicative, ajustable selon contraintes réelles du chantier.
</p>
