<table>
    <tbody>
        <tr>
            <td></td>
        </tr>
    </tbody>
</table>
<table>
    <thead>
        <tr>
            <th style="border: 1px solid black; font-weight: bold;">No</th>
            <th style="border: 1px solid black; font-weight: bold;">Puskesmas</th>
            <th style="border: 1px solid black; font-weight: bold;">Nama</th>
            <th style="border: 1px solid black; font-weight: bold;">Umur</th>
            <th style="border: 1px solid black; font-weight: bold;">Jenis Kelamin</th>
            <th style="border: 1px solid black; font-weight: bold;">No Telp</th>
            <th style="border: 1px solid black; font-weight: bold;">Nama PMO</th>
            <th style="border: 1px solid black; font-weight: bold;">No Telp PMO</th>
            <th style="border: 1px solid black; font-weight: bold;">Hub dengan Pasien</th>
            <th style="border: 1px solid black; font-weight: bold;">Lama Menderita TB</th>
            <th style="border: 1px solid black; font-weight: bold;">Lama Minum Obat</th>
            <th style="border: 1px solid black; font-weight: bold;">Nama Obat</th>
            <th style="border: 1px solid black; font-weight: bold;">Persentase Kepatuhan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($col as $c)
            <tr>
                <td style="border: 1px solid black;">{{ $c['no'] }}</td>
                <td style="border: 1px solid black;">{{ $c['puskesmas'] }}</td>
                <td style="border: 1px solid black;">{{ $c['nama'] }}</td>
                <td style="border: 1px solid black;">{{ $c['umur'] }}</td>
                <td style="border: 1px solid black;">{{ $c['jenis_kelamin'] }}</td>
                <td style="border: 1px solid black;">{{ $c['no_telp'] }}</td>
                <td style="border: 1px solid black;">{{ $c['nama_pmo'] }}</td>
                <td style="border: 1px solid black;">{{ $c['no_telp_pmo'] }}</td>
                <td style="border: 1px solid black;">{{ $c['hub_dg_pasien'] }}</td>
                <td style="border: 1px solid black;">{{ $c['lama_mdtb'] }}</td>
                <td style="border: 1px solid black;">{{ $c['lama_minum_obat'] }}</td>
                <td style="border: 1px solid black;">{{ $c['nama_obat'] }}</td>
                <td style="border: 1px solid black;">{{ $c['persentase_kepatuhan'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
