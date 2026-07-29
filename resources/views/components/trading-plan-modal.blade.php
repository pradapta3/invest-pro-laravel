<div
    x-data="{
        open: false,
        ticker: '', price: '', trend: '', forecast: '', analysis: '',
        init() {
            window.addEventListener('open-trading-plan-modal', (e) => {
                this.ticker = e.detail.ticker;
                this.price = e.detail.price;
                this.trend = e.detail.trend;
                this.forecast = e.detail.forecast;
                this.analysis = window.marked ? marked.parse(e.detail.ai_analysis) : e.detail.ai_analysis;
                this.open = true;
            });
        },
    }"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
>
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between">
            <h3 class="font-bold"><i class="fa-solid fa-robot mr-2"></i>Prophet AI: <span x-text="ticker"></span></h3>
            <button @click="open = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-3 gap-3 text-center mb-4">
                <div class="border border-slate-200 rounded-lg p-3 bg-slate-50">
                    <div class="text-[11px] font-bold text-slate-500 uppercase">Harga</div>
                    <div class="font-bold" x-text="price"></div>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 bg-slate-50">
                    <div class="text-[11px] font-bold text-slate-500 uppercase">Tren</div>
                    <div class="font-bold" x-text="trend"></div>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 bg-slate-50">
                    <div class="text-[11px] font-bold text-slate-500 uppercase">Forecast</div>
                    <div class="font-bold text-primary" x-text="forecast"></div>
                </div>
            </div>
            <div class="border border-slate-200 rounded-lg p-3">
                <h4 class="font-bold text-primary text-sm mb-2">Analisa Gemini:</h4>
                <div class="text-sm prose prose-sm max-w-none" x-html="analysis"></div>
            </div>
        </div>
    </div>
</div>
