import {
  MOCK_MENU_DATA,
  MOCK_PRODUCT_DATA,
  MOCK_TABLE_DATA,
} from './table-data';

export function getAllMenusApi() {
  return Promise.resolve(MOCK_MENU_DATA);
}

export function getExampleTableApi(params: Record<string, any>) {
  const { page = 1, pageSize = 20 } = params;
  const items = MOCK_PRODUCT_DATA.slice((page - 1) * pageSize, page * pageSize);
  return Promise.resolve({ items, total: MOCK_PRODUCT_DATA.length });
}

export function getTableListApi(params: Record<string, any>) {
  const { page = 1, pageSize = 20 } = params;
  const items = MOCK_TABLE_DATA.slice((page - 1) * pageSize, page * pageSize);
  return Promise.resolve({ items, total: MOCK_TABLE_DATA.length });
}

export function uploadFile(_file: File) {
  return new Promise<{ url: string }>((resolve) => {
    setTimeout(() => {
      resolve({
        url: `https://picsum.photos/id/${Math.floor(Math.random() * 100)}/200/200`,
      });
    }, 1000);
  });
}
