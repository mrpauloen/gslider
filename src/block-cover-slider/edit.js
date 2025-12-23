import { useEffect, useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl, TextControl, Button } from '@wordpress/components';

export default function Edit( props ) {
  const { attributes, setAttributes } = props;
  const {
    enabled = true,
    intervalMs = 10000,
    fadeMs = 900,
    overlayDim = 30,
    targetCoverSelector = '.wp-block-cover.WPBlockCoverSlider',
    sourceSelector = '.czik-hero-rotator__src',
    debugHighlight = true,
  } = attributes;

  const [ pinned, setPinned ] = useState( false );

  useEffect( () => {
    return () => {
      const els = document.querySelectorAll( '.czik-coverslider--highlight' );
      els.forEach( el => el.classList.remove( 'czik-coverslider--highlight' ) );
    };
  }, [] );

  function setAttr( key, val ) {
    setAttributes( { [ key ]: val } );
  }

  function highlightOn() {
    try {
      const targets = document.querySelectorAll( targetCoverSelector );
      const srcs = document.querySelectorAll( sourceSelector );
      targets.forEach( t => t.classList.add( 'czik-coverslider--highlight' ) );
      srcs.forEach( s => s.classList.add( 'czik-coverslider--highlight' ) );
    } catch ( e ) {
      // silent
    }
  }

  function highlightOff() {
    try {
      const els = document.querySelectorAll( '.czik-coverslider--highlight' );
      els.forEach( el => el.classList.remove( 'czik-coverslider--highlight' ) );
    } catch ( e ) {
      // silent
    }
  }

  const blockProps = useBlockProps( { className: 'gslider-placeholder' } );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Cover Slider">
          <ToggleControl label="Enabled" checked={ !! enabled } onChange={ val => setAttr( 'enabled', val ) } />
          <RangeControl label="Interval (ms)" value={ intervalMs } onChange={ val => setAttr( 'intervalMs', Number( val ) ) } min={ 1000 } max={ 60000 } />
          <RangeControl label="Fade (ms)" value={ fadeMs } onChange={ val => setAttr( 'fadeMs', Number( val ) ) } min={ 100 } max={ 5000 } />
          <ToggleControl label="Debug Highlight" checked={ !! debugHighlight } onChange={ val => setAttr( 'debugHighlight', val ) } />
          <TextControl label="Target selector" value={ targetCoverSelector } onChange={ val => setAttr( 'targetCoverSelector', val ) } />
          <TextControl label="Source selector" value={ sourceSelector } onChange={ val => setAttr( 'sourceSelector', val ) } />
        </PanelBody>
      </InspectorControls>

      <div { ...blockProps } onMouseEnter={ () => { if ( debugHighlight ) highlightOn(); } } onMouseLeave={ () => { if ( debugHighlight && ! pinned ) highlightOff(); } }>
        <div className="gslider-placeholder__icon">🎞️</div>
        <div className="gslider-placeholder__title">Cover Slider: { enabled ? 'aktywny' : 'wyłączony' }</div>
        <div className="gslider-placeholder__meta">Target: { targetCoverSelector }</div>
        <div className="gslider-placeholder__meta">Source: { sourceSelector }</div>
        <div className="gslider-placeholder__meta">Interval: { intervalMs }ms</div>
        <Button isPrimary onClick={ () => { const next = ! pinned; setPinned( next ); if ( next ) { highlightOn(); } else { highlightOff(); } } }>{ pinned ? 'Unpin highlight' : 'Podświetl' }</Button>
      </div>
    </>
  );
}
