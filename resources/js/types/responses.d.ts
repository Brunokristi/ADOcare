interface IIndexSuccessResponse<T> {
    items: T[];
    count: number;
}
type IIndexResponse<T> = Partial<IIndexSuccessResponse<T> | IErrorResponse>;



interface IPaginationMeta {
    page: number;
    perPage: number;
    totalPages: number;

}
type IPaginatedIndexResponse<T> = IIndexResponse<T> & { meta?: IPaginationMeta };
type IPaginatedIndexSuccessResponse<T> = IIndexSuccessResponse<T> & { meta: IPaginationMeta };


type IShowResponse<T> = Partial<IIShowSuccessResponse<T> | IErrorResponse>;
interface IShowSuccessResponse<T> {
    message: string;
    data: T;
}





interface IErrorResponse {
    message: string;
    errors: Array<any>;
}

