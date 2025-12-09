interface IErrorResponse {
    message: string;
    errors: Array<any>;
}

//show
interface IShowSuccessResponse<T> {
    message: string;
    data: T;
}
type IShowResponse<T> = Partial<IShowSuccessResponse<T> | IErrorResponse>;


// index
interface IIndexSuccessResponse<T> {
    message: string;
    data: IIndexSuccessResponsePayload<T>;
}
interface IIndexSuccessResponsePayload<T> {
    items: T[];
    count: number;
}
type IIndexResponse<T> = Partial<IIndexSuccessResponse<T> | IErrorResponse>;


// index with pagination
interface IPaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}
type IIndexSuccessPaginatedResponsePayload<T> = IIndexSuccessResponsePayload<T> & {
    meta: IPaginationMeta;
};
type IPaginatedIndexSuccessResponse<T> = IIndexSuccessResponse<T> & { data: IIndexSuccessPaginatedResponsePayload<T> };
type IPaginatedIndexResponse<T> = Partial<IPaginatedIndexSuccessResponse<T> | IErrorResponse>;


