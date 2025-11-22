interface IBranch {
    id: number;
    company_id: number;
    code: string;
    identificator: string;
    address: string;
    city: string;
    psc: string;
    phone: string;
    email: string;
    latitude: number;
    longitude: number;
    created_at: string;
    updated_at: string;
    pivot: {
        user_id: number;
        branch_id: number;
    };
}

interface ICompany {
    id: number;
    name: string;
    ico: string | null;
    dic: string | null;
    ic_dph: string | null;
    iban: string | null;
    bic: string | null;
    register: string | null;
    address: string;
    city: string;
    psc: string;
    phone: string;
    email: string;
    latitude: number;
    longitude: number;
    created_at: string;
    updated_at: string;
    laravel_through_key: number;
}

interface IUser {
    id: number;
    first_name: string;
    last_name: string;
    title: string | null;
    code: string;
    phone_number: string | null;
    email: string;
    login: string;
    initials: string;
    created_at: string;
    updated_at: string;
    branches: IBranch[];
    company: ICompany;
    roles_list: string[];
}
