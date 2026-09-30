interface TableRowData {
  address: string;
  age: number;
  id: number;
  name: string;
  nickname: string;
  role: string;
}

const roles = ['User', 'Admin', 'Manager', 'Guest'];

const categoryList = ['Electronics', 'Clothing', 'Food', 'Books', 'Toys'];
const colorList = ['red', 'blue', 'green', 'yellow', 'purple'];
const statusList = ['success', 'warning', 'error'] as const;

export const MOCK_TABLE_DATA: TableRowData[] = (() => {
  const data: TableRowData[] = [];
  for (let i = 0; i < 40; i++) {
    data.push({
      address: `New York${i}`,
      age: i + 1,
      id: i,
      name: `Test${i}`,
      nickname: `Test${i}`,
      role: roles[Math.floor(Math.random() * roles.length)] as string,
    });
  }
  return data;
})();

export const MOCK_TREE_TABLE_DATA = [
  {
    date: '2020-08-01',
    id: 10_000,
    name: 'Test1',
    parentId: null,
    size: 1024,
    type: 'mp3',
  },
  {
    date: '2021-04-01',
    id: 10_050,
    name: 'Test2',
    parentId: null,
    size: 0,
    type: 'mp4',
  },
  {
    date: '2020-03-01',
    id: 24_300,
    name: 'Test3',
    parentId: 10_050,
    size: 1024,
    type: 'avi',
  },
  {
    date: '2021-04-01',
    id: 20_045,
    name: 'Test4',
    parentId: 24_300,
    size: 600,
    type: 'html',
  },
  {
    date: '2021-04-01',
    id: 10_053,
    name: 'Test5',
    parentId: 24_300,
    size: 0,
    type: 'avi',
  },
  {
    date: '2021-10-01',
    id: 24_330,
    name: 'Test6',
    parentId: 10_053,
    size: 25,
    type: 'txt',
  },
  {
    date: '2020-01-01',
    id: 21_011,
    name: 'Test7',
    parentId: 10_053,
    size: 512,
    type: 'pdf',
  },
  {
    date: '2021-06-01',
    id: 22_200,
    name: 'Test8',
    parentId: 10_053,
    size: 1024,
    type: 'js',
  },
  {
    date: '2020-11-01',
    id: 23_666,
    name: 'Test9',
    parentId: null,
    size: 2048,
    type: 'xlsx',
  },
  {
    date: '2021-06-01',
    id: 23_677,
    name: 'Test10',
    parentId: 23_666,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 23_671,
    name: 'Test11',
    parentId: 23_677,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 23_672,
    name: 'Test12',
    parentId: 23_677,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 23_688,
    name: 'Test13',
    parentId: 23_666,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 23_681,
    name: 'Test14',
    parentId: 23_688,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 23_682,
    name: 'Test15',
    parentId: 23_688,
    size: 1024,
    type: 'js',
  },
  {
    date: '2020-10-01',
    id: 24_555,
    name: 'Test16',
    parentId: null,
    size: 224,
    type: 'avi',
  },
  {
    date: '2021-06-01',
    id: 24_566,
    name: 'Test17',
    parentId: 24_555,
    size: 1024,
    type: 'js',
  },
  {
    date: '2021-06-01',
    id: 24_577,
    name: 'Test18',
    parentId: 24_555,
    size: 1024,
    type: 'js',
  },
];

export const MOCK_PRODUCT_DATA = (() => {
  const data = [];
  for (let i = 0; i < 68; i++) {
    data.push({
      category: categoryList[i % categoryList.length] ?? '',
      color: colorList[i % colorList.length] ?? '',
      id: `${i}`,
      imageUrl: `https://picsum.photos/id/${i + 10}/100/100`,
      open: Math.random() > 0.5,
      price: `$${(Math.random() * 1000).toFixed(2)}`,
      productName: `Product ${i}`,
      releaseDate: new Date(Date.now() - Math.random() * 1e10).toISOString(),
      status: statusList[i % statusList.length] ?? '',
    });
  }
  return data;
})();

export const MOCK_MENU_DATA = [
  {
    name: 'Dashboard',
    path: '/dashboard',
    children: [
      { name: 'Analytics', path: '/analytics' },
      { name: 'Workspace', path: '/workspace' },
    ],
  },
  {
    name: 'System',
    path: '/system',
    children: [
      { name: 'Users', path: '/system/users' },
      { name: 'Roles', path: '/system/roles' },
      { name: 'Menus', path: '/system/menus' },
    ],
  },
];
