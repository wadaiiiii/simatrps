from pathlib import Path

PATH = Path('resources/js/pages/rps/show.tsx')
text = PATH.read_text(encoding='utf-8')


def replace_once(old: str, new: str, label: str):
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected 1 match, found {count}')
    text = text.replace(old, new, 1)

replace_once(
    '<div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>',
    '''<div>\n                            <div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>\n                            <div className="mt-0.5 text-[10px] text-slate-400">\n                                Mulai dengan Atur Pertemuan, lalu lengkapi isi RPS pekanan.\n                            </div>\n                        </div>''',
    'weekly toolbar guidance',
)

replace_once(
    '>\n                                Atur Pertemuan\n                            </button>',
    '>\n                                1. Atur Pertemuan\n                            </button>',
    'meeting planner button label',
)

replace_once(
    '>\n                                Isi Bagian Kosong\n                            </button>',
    '>\n                                2. Isi Bagian Kosong\n                            </button>',
    'fill empty button label',
)

replace_once(
    '<th className="w-36 border-b border-slate-200 px-3 py-2 text-center">Pertemuan</th>',
    '<th className="w-36 border-b border-slate-200 px-3 py-2 text-center">Banyak Pertemuan</th>',
    'meeting count column label',
)

PATH.write_text(text, encoding='utf-8')
print('Meeting-first weekly RPS flow applied.')
