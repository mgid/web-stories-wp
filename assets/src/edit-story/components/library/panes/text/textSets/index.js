/*
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import styled from 'styled-components';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Section } from '../../../common';
import { UnitsProvider } from '../../../../../units';
import { TEXT_SET_SIZE } from '../../../../../constants';
import { getTextSets } from './utils';
import TextSet from './textSet';

// TODO: max-height should be dynamically calculated
// based on height of window.
const TextSetContainer = styled.div`
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap: 12px;
  overflow: auto;
  max-height: 280px;
`;

function TextSets() {
  const [textSets, setTextSets] = useState([]);

  useEffect(() => {
    getTextSets().then((sets) => setTextSets(sets));
  }, []);
  return (
    <Section title={__('Text Sets', 'web-stories')}>
      <TextSetContainer>
        <UnitsProvider
          pageSize={{
            width: TEXT_SET_SIZE,
            height: TEXT_SET_SIZE,
          }}
        >
          {textSets.map((elements, index) => (
            <TextSet key={index} elements={elements} index={index} />
          ))}
        </UnitsProvider>
      </TextSetContainer>
    </Section>
  );
}

export default TextSets;
