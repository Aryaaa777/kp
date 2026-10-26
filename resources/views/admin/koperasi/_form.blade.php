@php
    $cooperative = $cooperative ?? null;
    $action = $action ?? route('admin.koperasi.store');
    $method = $method ?? 'POST';
    $jenisOptions = $jenisOptions ?? [];
    $statusOperasionalOptions = $statusOperasionalOptions ?? [];
    $statusOperasionalPlaceholder = $cooperative === null
        ? '— Pilih status operasional —'
        : ($cooperative->status_operasional === null ? '— Belum ditetapkan —' : null);

    $currentRegion = $cooperative?->region;
    $currentProvince = $currentRegion?->ancestorOfType('province');
    $currentRegency = $currentRegion?->ancestorOfType('regency')
        ?? $currentRegion?->ancestorOfType('city');
    $currentDistrict = $currentRegion?->ancestorOfType('district');

    $selectedProvince = old('province_id', $currentProvince?->id);
    $selectedRegency = old('regency_id', $currentRegency?->id);
    $selectedDistrict = old('district_id', $currentDistrict?->id);
    $selectedRegion = old('region_id', $cooperative?->region_id);

    $selectClass = 'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Identitas</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="nama" label="Nama koperasi" :value="$cooperative?->nama" required />

            <x-form.input name="nomor_identitas" label="Nomor identitas" :value="$cooperative?->nomor_identitas" required
                help="Format resmi belum dikonfirmasi dinas (Q-001). Isi sesuai data yang tersedia; sistem hanya memastikan unik." />

            @if (count($jenisOptions))
                <x-form.select name="jenis_koperasi" label="Jenis koperasi" required
                    :options="collect($jenisOptions)->mapWithKeys(fn ($item) => [$item => $item])->all()"
                    :selected="$cooperative?->jenis_koperasi" />
            @else
                <x-form.input name="jenis_koperasi" label="Jenis koperasi" :value="$cooperative?->jenis_koperasi" required
                    help="Daftar jenis resmi belum dikonfirmasi dinas (Q-003); masukkan teks bebas sementara." />
            @endif

            <x-form.input name="bentuk_koperasi" label="Bentuk koperasi" :value="$cooperative?->bentuk_koperasi" />

            <x-form.input name="tanggal_berdiri" type="date" label="Tanggal berdiri"
                :value="$cooperative?->tanggal_berdiri?->format('Y-m-d')" />

            <x-form.select name="status_verifikasi" label="Status data" required
                :options="$statusOptions" :selected="$cooperative?->status_verifikasi ?? 'draft'"
                help="Penjaga matriks transisi resmi mengikuti workflow verifikasi (F5). Status “Diarsipkan” hanya melalui aksi arsip." />

            <x-form.select name="status_operasional" label="Status operasional"
                :options="$statusOperasionalOptions"
                :selected="$cooperative?->status_operasional"
                :placeholder="$statusOperasionalPlaceholder"
                :required="$cooperative === null"
                help="Wajib dipilih saat menambah koperasi. Data lama yang belum dikonfirmasi tetap kosong sampai dilengkapi pihak berwenang." />
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Wilayah &amp; kontak</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @if ($provinces->isEmpty())
                <div class="sm:col-span-2">
                    <x-alert type="warning">
                        Referensi wilayah belum tersedia. Jalankan <code>php artisan regions:import</code>
                        dengan berkas resmi sebelum mengisi wilayah koperasi.
                    </x-alert>
                </div>
            @else
                <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2" data-region-cascade>
                    <div>
                        <label for="province_id" class="block text-sm font-medium text-slate-700">Provinsi</label>
                        <select id="province_id" name="province_id" data-level="province" class="{{ $selectClass }}">
                            <option value="">— Pilih provinsi —</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}" @selected((string) $selectedProvince === (string) $province->id)>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-form.error name="province_id" />
                    </div>

                    <div>
                        <label for="regency_id" class="block text-sm font-medium text-slate-700">Kabupaten/Kota</label>
                        <select id="regency_id" name="regency_id" data-level="regency"
                            data-parent-select="province_id" class="{{ $selectClass }}">
                            <option value="">— Pilih kabupaten/kota —</option>
                            @foreach ($regencies as $regency)
                                <option value="{{ $regency->id }}" data-parent="{{ $regency->parent_id }}"
                                    @selected((string) $selectedRegency === (string) $regency->id)>
                                    {{ $regency->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-form.error name="regency_id" />
                    </div>

                    <div>
                        <label for="district_id" class="block text-sm font-medium text-slate-700">Kecamatan</label>
                        <select id="district_id" name="district_id" data-level="district"
                            data-parent-select="regency_id" class="{{ $selectClass }}">
                            <option value="">— Pilih kecamatan —</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" data-parent="{{ $district->parent_id }}"
                                    @selected((string) $selectedDistrict === (string) $district->id)>
                                    {{ $district->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-form.error name="district_id" />
                    </div>

                    <div>
                        <label for="region_id" class="block text-sm font-medium text-slate-700">Desa/Kelurahan</label>
                        <select id="region_id" name="region_id" data-level="village"
                            data-parent-select="district_id" class="{{ $selectClass }}">
                            <option value="">— Pilih desa/kelurahan —</option>
                            @if ($currentRegion !== null && $currentRegion->type !== 'village')
                                <option value="{{ $currentRegion->id }}"
                                    data-parent="{{ $currentRegion->type === 'district' ? $currentRegion->id : '' }}"
                                    @selected((string) $selectedRegion === (string) $currentRegion->id)>
                                    {{ $currentRegion->name }} (wilayah saat ini)
                                </option>
                            @endif
                            @foreach ($villages as $village)
                                <option value="{{ $village->id }}" data-parent="{{ $village->parent_id }}"
                                    @selected((string) $selectedRegion === (string) $village->id)>
                                    {{ $village->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-form.error name="region_id" />
                    </div>

                    <p class="sm:col-span-2 text-xs text-slate-500">
                        Pilih berurutan dari provinsi sampai desa/kelurahan. Alamat publik dibentuk dari
                        hierarki ini; alamat detail (jalan/RT/RW) tidak dipublikasikan.
                    </p>
                </div>
            @endif

            <x-form.input name="telephone" label="Telepon" :value="$cooperative?->telephone" />
            <x-form.input name="email" type="email" label="Email" :value="$cooperative?->email" />
            <x-form.input name="website" type="url" label="Website" :value="$cooperative?->website" placeholder="https://" />

            <div class="sm:col-span-2">
                <x-form.textarea name="alamat" label="Alamat" :value="$cooperative?->alamat" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $cooperative ? route('admin.koperasi.show', $cooperative) : route('admin.koperasi.index') }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
