/** Bentuk data yang dikirim server lewat API Resource. Uang selalu integer rupiah. */

export interface UserData {
    id: number;
    name: string;
    email: string | null;
}

export type StatusTone = 'lunas' | 'menunggu' | 'jatuh-tempo' | 'kosong';
