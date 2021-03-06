import Injector from 'lib/Injector';
import { fileInterface, file, folder } from 'lib/fileFragments';

const registerQueries = () => {
  Injector.query.registerFragment('FileInterfaceFields', fileInterface);
  Injector.query.registerFragment('FileFields', file);
  Injector.query.registerFragment('FolderFields', folder);
};

export default registerQueries;
