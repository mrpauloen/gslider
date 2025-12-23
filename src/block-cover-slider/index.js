import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';
import save from './save';
import './editor.css';

registerBlockType( 'gslider/block-cover-slider', {
  edit,
  save,
} );
